# App

This project was generated using [Angular CLI](https://github.com/angular/angular-cli) version 22.1.7.

## Development server

To start a local development server, run:

```bash
ng serve
```

Once the server is running, open your browser and navigate to `http://localhost:4200/`. The application will automatically reload whenever you modify any of the source files.

### Talking to the Laravel backend in dev

The Angular dev server (default `http://localhost:4200`) cannot fetch
`/api/...` cross-origin from Laravel (`http://localhost:8000`) directly.
Cross-origin/dev connectivity is handled with the **Angular dev-server proxy**
(not a CORS change): `angular.json` sets `serve.options.proxyConfig` to
`proxy.conf.json`, which forwards every `/api` request to
`http://127.0.0.1:8000` in the browser's own origin (no CORS needed, no
`Access-Control-Allow-Origin` workaround).

- Proxy rule: `/api` -> `http://127.0.0.1:8000` (`changeOrigin: true`).
- The client talks to Laravel as `/api/v1/...` relative URLs, so no
  hard-coded host lives in the app code.

Start the backend with `php artisan serve` (Laravel) on port 8000, then
`ng serve` on 4200. Open http://localhost:4200/today.

## Code scaffolding

Angular CLI includes powerful code scaffolding tools. To generate a new component, run:

```bash
ng generate component component-name
```

For a complete list of available schematics (such as `components`, `directives`, or `pipes`), run:

```bash
ng generate --help
```

## Building

To build the project run:

```bash
ng build
```

This will compile your project and store the build artifacts in the `dist/` directory. By default, the production build optimizes your application for performance and speed.

## Running unit tests

To execute unit tests with the [Vitest](https://vitest.dev/) test runner, use the following command:

```bash
ng test
```

## Running end-to-end tests

For end-to-end (e2e) testing, run:

```bash
ng e2e
```

Angular CLI does not come with an end-to-end testing framework by default. You can choose one that suits your needs.

## Additional Resources

For more information on using the Angular CLI, including detailed command references, visit the [Angular CLI Overview and Command Reference](https://angular.dev/tools/cli) page.
