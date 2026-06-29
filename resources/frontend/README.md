# Frontend sources

Editable application CSS and JavaScript live here. Files are grouped by feature and are not served directly.

## Structure

- `shared/js` contains helpers and the authenticated HTTP client used by multiple features.
- `app/js` contains the application shell and screen-level behavior.
- `app/js/features` contains feature modules such as onboarding, profile, history and meal draft.
- `app/css/base` contains tokens, reset and global layout rules.
- `app/css/components` contains reusable UI components.
- `app/css/features` and `app/css/screens` contain feature-specific styles.

Large features are split by responsibility. For example, meal draft products have separate rendering,
interaction and draft-state modules; history calendar has separate data, view and binding modules.

Build browser assets after changing these files:

```bash
composer build:frontend
```

The command reads the explicit module order from `scripts/build_frontend.php` and writes:

- `public/assets/app/dist/app.css`
- `public/assets/app/dist/app.js`

Keep generated bundles in version control so deployment does not require Node.js or an additional frontend toolchain.

The files in `public/assets/app/dist` are generated delivery artifacts, not a second editable source tree.
Make changes only in `resources/frontend`, then rebuild the bundles.
