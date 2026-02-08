# Agent Workflow

- After making code changes, commit them and push to the current git branch by default.
- If the user explicitly asks not to commit or not to push, follow the user request.
- Keep commits focused and use clear, descriptive commit messages.
- After creating a new branch, update deploy.yml so deployments are triggered on pushes to that branch.