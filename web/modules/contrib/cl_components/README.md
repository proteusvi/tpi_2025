_CL Components_ lets you declare Drupal components that you can import and
render in your Drupal site. Other solutions, like
the [Components module](https://www.drupal.org/project/components), only concern
themselves with templates, and not components. But a **template is only a part
of a component**. The Components module is compatible with CL Components.

What do you mean by "component"?
--------------------------------

In this context, a component is the combination of:

* A regular Twig template.
* Metadata describing the input data the template accepts (
  the `my-component.component.yml`
  file).
* Optional JavaScript.
* Optional Styles.

A component then **needs to be embedded in your _Drupal templates_**, otherwise
it just sits there waiting to be used. (Drupal templates are the templates that
get used by naming them with the correct convention,
like `node--article--card.twig.html`) In this context, a component is **not**: a
type of a block plugin (
like [Component](https://www.drupal.org/project/component)
and [Decoupled Blocks](https://www.drupal.org/project/pdb) modules), a Twig
template by itself (like
the [Components](http://www.drupal.org/project/components) module). These are
more comparable to
the [JS Widgets modules](https://www.lullabot.com/articles/progressive-decoupling-made-easy)
. This module treats components in a very similarly to the (
brilliant) [Single File Components](https://www.drupal.org/project/sfc) module,
but doesn't take an opinion on how to embed components inside your _Drupal
templates_, and with a more familiar syntax for Drupal
developers. [![A component's structure](https://www.drupal.org/files/ksnip_20220313-095710.png)](https://www.drupal.org/files/ksnip_20220313-095710.png)

How to create a component
-------------------------

A component is any directory in your Drupal install that contains
a `my-component.component.yml`. This folder must also contain at least
a `my-component.twig` template. It is advised to create a `css` and `js`
directory for your stylesheets and JS scripts. Learn more about creating a
component in
the [documentation](https://git.drupalcode.org/project/cl_components/-/blob/1.x/docs/writing-components.md)
. You will need to let Drupal know where to start scanning for components (
including subdirectories). You can add as many locations as you need in the
settings page (`admin/config/user-interface/cl-components`).

How to render a component
-------------------------

In Drupal there are two main ways to render some HTML, via a render array, and
via Twig.

### Using a render array

There is a special render element that allows you to render a component based
solely on the component ID and the component props. See this example on
rendering the `my-button` component with the label _Click Me_ using
the `hook_page_top`:

```php
// hook_page_top
$page_top['cta-button'] = [
  '#type' => 'cl_component',
  '#component' => 'my-button',
  '#variant' => 'primary',
  '#context' => [
    'text' => t('Click Me'),
  ],
];
```

This will take care of CSS, JS, HTML, and assets for you.

### Via Twig templates

There are two syntaxes for rendering components, both of those will offer these
features:

![](https://www.drupal.org/files/1F5BC_black.png)

### Easy to embed

Embed your component like you always embedded your template.

_You can use the familiar `include/embed` with the path to the component's
template_

![](https://www.drupal.org/files/1F4DA_black.png)

### Libraries included

JS and CSS files inside the component folder will be **included** during render.

_For caching reasons you can include `cl_components/all` in your theme. This
library includes the CSS and JS for all the components._

![](https://www.drupal.org/files/1F6C4_black.png)

### Additional context

The templates for your components will receive **additional context** that will
make your theming experience more flexible and powerful.

_Learn more about the additional context
in [the documentation](https://git.drupalcode.org/project/cl_components/-/blob/1.x/docs/writing-components.md#twig-templates)
._

#### With the traditional syntax: `include` or `embed`

Just provide the ID of the component. CL Components will check if the template
is for a components, and add the CSS & JS if it is. Example:

```twig
{% include "my-button--primary" with {
  text: 'Click Me',
  iconType: 'external'
} %}
```

Composing "top-down" components
-------------------------------

**Traditionally**, when you are not using this module, in order to map your
templates, you need to start at the content type template (for instance) and
then at some point you render `{{ content }}`. This is where the render pipeline
enters the cognitive black hole, you lose the thread. To find the thread again
you need to leave the IDE to your browser to inspect the HTML in search of
template name suggestions for your fields/sub-components. After that you create
the new twig template, etc. **With this module you still start at the content
type template and you do everything there, if you want.** That is because you
will develop all your components separately and wire the data to in the Drupal
templates. Consider the following example for a custom
block: [![Component from Twig](https://www.drupal.org/files/ksnip_20220418-005414.png)](https://www.drupal.org/files/ksnip_20220418-005414.png)
The `my-card` component could be provided by a contributed module, a custom
module for your component library, or in the same theme that
holds `block--bundle--block-cta.twig.html`. Note that the `my-card` component
supports children HTML and children components, _similar_ to **how React works**
. With this technique you can map all the field data in its place by using the
necessary sub-components. Thus, you don't have to find the sub-templates to map
them to their corresponding components.

**Tip**

leverage the [No Markup module](https://www.drupal.org/project/nomarkup) in
order to easily clean out wrapping field markup from
the `{{ content.field_name }}` in your templates.
See [more info](https://www.drupal.org/project/nomarkup) on why
avoiding `{{ content.field_name|render|striptags }}`
and `{{ node.field_name[0].getValue }}` is important.

* * *

**Build a component library!**
------------------------------

Now that we have components we can build a component library. Thanks
to [CL Server](https://www.drupal.org/project/cl_server) this is super easy!

I recommend [Storybook](https://storybook.js.org), but others are great options
as well.

### [💧_Component Libraries: Server_ Drupal module](https://www.drupal.org/project/cl_server)

### [📚 Storybook Drupal Addon](https://github.com/lullabot/storybook-drupal-addon)

[![Storybook integration](https://www.drupal.org/files/ksnip_20220316-164333.png)](https://www.drupal.org/files/ksnip_20220316-164333.png)
