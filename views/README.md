# views/

Top-level `views/` is the default Perique view path. `App_Factory::default_setup()`
sets it to `{base_path}/views`, which `perique-bootstrap.php` resolves to this
folder via `new App_Factory( PINKCRAB_COMMENT_MODERATION_PATH )`. You can override it in
`config/settings.php` (`path.view` / `url.view`) if you need to.

## Layout

```
views/
├── components/        # Component templates — auto-resolved from class names
│   └── *.php          (kebab-case of the FQCN tail)
└── <other folders>/   # Templates rendered via $view->render( 'folder/name' )
```

## Components

`Component` PHP classes live in [src/Presentation/View/Component/](../src/Presentation/View/Component/).
Their templates live in [components/](components/). Perique resolves the
template by, in order:

1. `Hooks::COMPONENT_ALIASES` filter mapping.
2. A `@view` docblock annotation on the class.
3. A `public function template(): ?string` method on the class.
4. The class name converted to kebab-case.

For example, a hypothetical `My_Card_Component` would use option 4 — kebab-case
of `My_Card_Component` is `my-card-component`, so its template would be
`views/components/my-card-component.php`.

Inside the template `$this` is bound to the View (Renderable), not the
Component. You read component properties as `$this->name` (etc.) — Perique
passes every property of the Component to the template automatically. You
can nest more views via `$this->render(...)`, `$this->component(...)`,
`$this->view_model(...)`.

## Other templates

Render plain templates from any class with an injected `View`:

```php
$this->view->render( 'admin/some-page', [ 'foo' => 'bar' ] );
// Resolves to views/admin/some-page.php
```

Dot-notation is supported too: `'admin.some-page'` is equivalent to `'admin/some-page'`.

## View_Model

For repeatedly-rendered (template, data) pairs, define a `View_Model` class
in [src/Presentation/View/Model/](../src/Presentation/View/Model/) and render
with `$view->view_model( $vm )`. Keeps the contract in one place.
