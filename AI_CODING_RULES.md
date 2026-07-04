# WordPress Plugin Development Rules

- Follow WordPress Coding Standards for PHP.
- Use the plugin slug and the alynt_ prefix family for functions, options, hooks, nonces, and handles.
- Escape output, sanitize input, verify nonces, and check capabilities on every admin action.
- Do not log API tokens, authorization headers, or presigned URLs.
- Keep Drime API details isolated in the Drime client class.
- Keep WPvivid integration defensive. Prefer directory scanning unless a verified stable WPvivid hook is added later.
