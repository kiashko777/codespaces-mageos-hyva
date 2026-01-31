#!/bin/bash
set -Eeuo pipefail
IFS=$'\n\t'

# --------------------------------------------------
# 4. Missing Environment Checks (Fail Fast)
# --------------------------------------------------
: "${WORKER_AUTH_TOKEN:?Error: WORKER_AUTH_TOKEN is not set.}"
: "${CALLBACK_URL:?Error: CALLBACK_URL is not set.}"
: "${GITHUB_TOKEN:?Error: GITHUB_TOKEN is required for 'gh' commands.}"

# --------------------------------------------------
# Atomic lock with cleanup
# --------------------------------------------------
LOCK_FILE="/tmp/agent.lock"
TASK_FILE=""
RESEARCH_FILE=""

exec 9>"$LOCK_FILE" || exit 1
flock -n 9 || { echo "Agent already running. Exiting."; exit 0; }

cleanup() {
    echo "🧹 Cleaning up temporary files..."
    rm -f "$LOCK_FILE"
    [[ -n "$TASK_FILE" && -f "$TASK_FILE" ]] && rm -f "$TASK_FILE"
    [[ -n "$RESEARCH_FILE" && -f "$RESEARCH_FILE" ]] && rm -f "$RESEARCH_FILE"
}
trap cleanup EXIT

# --------------------------------------------------
# Dependency checks
# --------------------------------------------------
for cmd in gh jq curl gemini flock mktemp; do
    command -v "$cmd" >/dev/null || { echo "Missing dependency: $cmd"; exit 1; }
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CURL_OPTS=(-s -f --connect-timeout 5 --max-time 30)

# --------------------------------------------------
# Metadata extraction from Instance Name
# --------------------------------------------------
DISPLAY_NAME="$(gh cs view --json displayName | jq -r '.displayName')"

if [[ "$DISPLAY_NAME" =~ ^(.+)-(PLAN|DEVELOP)$ ]]; then
    export JIRA_ISSUE_KEY="${BASH_REMATCH[1]}"
    # Native Bash 4+ lowercase conversion
    export AGENT_MODE="${BASH_REMATCH[2],,}" 
else
    echo "Invalid display name format: $DISPLAY_NAME"
    exit 1
fi

echo "Ticket: $JIRA_ISSUE_KEY | Mode: $AGENT_MODE"

# --------------------------------------------------
# Temp files & Fetch Jira Task
# --------------------------------------------------
TASK_FILE="$(mktemp /tmp/task.XXXXXX.json)"
RESEARCH_FILE="$(mktemp /tmp/research.XXXXXX.md)"

FETCH_PAYLOAD="$(jq -n --arg issueKey "$JIRA_ISSUE_KEY" '{issueKey:$issueKey}')"

curl "${CURL_OPTS[@]}" -X POST \
    -H "Authorization: Bearer $WORKER_AUTH_TOKEN" \
    -H "Content-Type: application/json" \
    -d "$FETCH_PAYLOAD" \
    "$CALLBACK_URL/fetch-task" \
    -o "$TASK_FILE"

# --------------------------------------------------
# 2. Research Prompt 
# --------------------------------------------------
LOG_DIR="$SCRIPT_DIR/../.devcontainer/logs/$JIRA_ISSUE_KEY"
mkdir -p "$LOG_DIR"

# Construct the prompt logic
if [[ "$AGENT_MODE" == "plan" ]]; then
    ROLE="Technical Architect"
    EXTRA="Focus on impacted modules and logic flow. Do NOT write code yet."
else
    ROLE="Lead Developer"
    EXTRA="Identify specific file paths for modification and necessary test coverage."
fi

# We use a subshell to pipe the content directly to Gemini 
# to avoid the "Argument list too long" error.
echo "Generating $AGENT_MODE strategy..."

{
    cat <<EOF
You are a $ROLE. Mode: ${AGENT_MODE^^}.

Analyze this Jira Ticket content:
$(cat "$TASK_FILE")

$EXTRA
EOF
} | tee "$LOG_DIR/prompt.log" | gemini -y > "$RESEARCH_FILE"

# --------------------------------------------------
# Launch agent loop
# --------------------------------------------------
bash "$SCRIPT_DIR/run-agent-loop.sh" \
    "$TASK_FILE" \
    "$RESEARCH_FILE" \
    "$AGENT_MODE"
