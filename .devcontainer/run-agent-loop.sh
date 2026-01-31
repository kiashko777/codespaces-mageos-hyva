#!/bin/bash
set -euo pipefail

# --- 1. Environment & Non-Interactive Mode ---
export NG_CLI_ANALYTICS=false
export CI=true

# Project Paths
REPO_ROOT="$(git rev-parse --show-toplevel)"
PLATFORM_LOG="/tmp/platform.log"
AGENT_LOG_DIR="$REPO_ROOT/.devcontainer/logs"
mkdir -p "$AGENT_LOG_DIR"

# --- 2. Initialization ---
export TASK_FILE="${1:-/tmp/task.json}"
export RESEARCH_FILE="${2:-/tmp/RESEARCH.md}"
export MODE="${3:-develop}"

export ISSUE_KEY=$(jq -r '.key // "UNKNOWN"' "$TASK_FILE")
export SUMMARY=$(jq -r '.summary // "No Summary"' "$TASK_FILE")

echo "--- Gemini Agent Mode Started for $ISSUE_KEY ---" | tee -a "$PLATFORM_LOG"
echo "Logging detailed output to $AGENT_LOG_DIR" | tee -a "$PLATFORM_LOG"

if [ -n "${CALLBACK_URL:-}" ] && [ "$MODE" == "develop" ]; then
    curl -s -X POST -H "Authorization: Bearer $WORKER_AUTH_TOKEN" -H "Content-Type: application/json" \
         -d "{\"issueKey\": \"$JIRA_ISSUE_KEY\", \"targetStatus\": \"AI Dev In Progress\"}" \
         "$CALLBACK_URL/update-status" | tee -a "$PLATFORM_LOG"
fi

# --- 3. Branching ---
NEW_BRANCH="feature/${ISSUE_KEY}-agent-fix"
pushd "$REPO_ROOT" > /dev/null
git checkout "$NEW_BRANCH" 2>/dev/null || git checkout -b "$NEW_BRANCH"
START_HASH=$(git rev-parse HEAD)
popd > /dev/null

# --- 4. The Agent Execution ---
PLAN_CONTENT=$(cat "$RESEARCH_FILE")
WORKSPACE_CONTEXT=$(tree -I "node_modules|.git" -L 2 || echo "tree command unavailable")

AGENT_DIRECTIVE=$(cat <<EOF
You are a Senior Web Developer  Engineer. Work is being performed in the Magento 2 and Hyva Project

STRATEGY (The Plan):
$PLAN_CONTENT

OBJECTIVE (The Action):
$SUMMARY

CONTEXT:
$WORKSPACE_CONTEXT

INSTRUCTIONS:
1. Implement the STRATEGY.
2. Do NOT run any tests (ignore ng test).
3. Maintain a file named 'AI_CHANGE_LOG.md' in $AGENT_LOG_DIR.
4. In 'AI_CHANGE_LOG.md', always append:
   - The changes made
   - Tests that SHOULD verify these changes
5. When the objective is met and log is written, summarize your work.
EOF
)

AGENT_SPECIFIC_LOG="$AGENT_LOG_DIR/$ISSUE_KEY/agent.log"
mkdir -p "$(dirname "$AGENT_SPECIFIC_LOG")"
echo -e "\n\n--- AGENT DIRECTIVE: $(date) ---\n$AGENT_DIRECTIVE" >> "$AGENT_SPECIFIC_LOG"

echo "Launching Gemini Agent..." | tee -a "$PLATFORM_LOG"

# Retry Logic with exponential backoff
MAX_RETRIES=3
RETRY_COUNT=0
EXIT_CODE=1
SLEEP_TIME=5

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Attempt $RETRY_COUNT of $MAX_RETRIES..." | tee -a "$PLATFORM_LOG"
    
    gemini -y -p "$AGENT_DIRECTIVE" > >(tee -a "$AGENT_SPECIFIC_LOG") 2> >(tee -a "$AGENT_SPECIFIC_LOG" >&2)
    EXIT_CODE=$?

    if [ $EXIT_CODE -eq 0 ]; then
        echo "Gemini Agent completed successfully." | tee -a "$PLATFORM_LOG"
        break
    else
        echo "Gemini Agent failed (Exit Code: $EXIT_CODE)." | tee -a "$PLATFORM_LOG"
        if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
            echo "Retrying in $SLEEP_TIME seconds..." | tee -a "$PLATFORM_LOG"
            sleep $SLEEP_TIME
            SLEEP_TIME=$((SLEEP_TIME * 2))  # exponential backoff
        fi
    fi
done

# --- 5. Finalization & Reporting ---
pushd "$REPO_ROOT" > /dev/null

STATUS="failure"
FINAL_MESSAGE=""

if [ $EXIT_CODE -eq 0 ]; then
    # Commit only if there are changes
    if ! git diff-index --quiet HEAD --; then
        git add .
        git commit -m "feat($ISSUE_KEY): $SUMMARY"
    fi

    END_HASH=$(git rev-parse HEAD)

    if [ "$START_HASH" != "$END_HASH" ]; then
        STATUS="success"

        if [ "$MODE" != "plan" ]; then
            git push origin "$NEW_BRANCH"

            # Capture full diff safely
            DIFF_CONTENT=$(git diff "$START_HASH" "$END_HASH")
            AI_SUMMARY_MD=$(gemini -p "Summarize changes for PR body, focusing on 'why', for ticket $ISSUE_KEY: $DIFF_CONTENT" 2>/dev/null | sed '/Hook registry/d')

            AI_SUMMARY_JIRA=$(gemini -p "Convert Markdown summary to Jira Markup:
- h3. for headers
- * for bold
- {{ }} for inline code
- * for bullets
- Links as [text|url]

Content:
$AI_SUMMARY_MD" 2>/dev/null | sed '/Hook registry/d')

            PR_BODY=$(cat <<EOF
### Ticket: $ISSUE_KEY

$AI_SUMMARY_MD
EOF
)
            PR_URL=$(gh pr create --title "feat($ISSUE_KEY): $SUMMARY" --body "$PR_BODY" --base main 2>/dev/null || gh pr view --json url -q .url)

            FINAL_MESSAGE=$(cat <<EOF
h3. ✅ AI Agent Task Completed

*Summary of Actions:*
$AI_SUMMARY_JIRA

*Pull Request:* [$PR_URL|$PR_URL]
EOF
)
        fi
    else
        EXIT_CODE=0 # No changes made, soft warning
    fi
fi

# Failure handling
if [ "$STATUS" == "failure" ] && [ -z "$FINAL_MESSAGE" ]; then
    ERROR_TAIL=$(tail -n 50 "$AGENT_SPECIFIC_LOG" | sed 's/"/\\"/g' | tr '\n' ' ')
    if [ -z "$ERROR_TAIL" ]; then
        ERROR_TAIL="Unknown error. Agent exited with code $EXIT_CODE but produced no log."
    fi

    if [ $EXIT_CODE -ne 0 ]; then
        FINAL_MESSAGE="❌ *AI Agent Crashed* after $MAX_RETRIES attempts.

Last Error Log:
\`\`\`
$ERROR_TAIL
\`\`\`
"
    else
        FINAL_MESSAGE="⚠️ *AI Agent Finished but No Changes Detected*.

Check the task description or logs."
    fi
fi

# Build Payload safely with JQ
if [ -n "$FINAL_MESSAGE" ]; then
    JSON_PAYLOAD=$(jq -n \
      --arg ik "$ISSUE_KEY" \
      --arg st "$STATUS" \
      --arg msg "$FINAL_MESSAGE" \
      '{jobId: $ik, issueKey: $ik, status: $st, message: $msg}')
else
    JSON_PAYLOAD=$(jq -n \
      --arg ik "$ISSUE_KEY" \
      --arg st "$STATUS" \
      '{jobId: $ik, issueKey: $ik, status: $st}')
fi

echo "Sending completion report to callback..." | tee -a "$PLATFORM_LOG"
curl -s -X POST -H "Authorization: Bearer $WORKER_AUTH_TOKEN" -H "Content-Type: application/json" \
     -d "$JSON_PAYLOAD" \
     "${CALLBACK_URL}/complete"

popd > /dev/null
echo "--- Agent Process Finished ---" | tee -a "$PLATFORM_LOG"
