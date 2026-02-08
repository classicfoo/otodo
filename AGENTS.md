# Agent Workflow

- After making code changes, commit them and push to the current git branch by default.
- If the user explicitly asks not to commit or not to push, follow the user request.
- Keep commits focused and use clear, descriptive commit messages.
- Whenever creating a new branch, update the branch name in `.github/workflows/deploy.yml` so deploys track that branch.

## Session notice

- At the start of every session, print the following message verbatim:

"Network access may be disabled. Please enable permissions if you would like the agent to have internet access."
