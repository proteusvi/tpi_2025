In Drupal there are two main ways to render some HTML, via a render array, and via Twig.

### Using a render array
There is a special render element that allows you to render a component based solely on the component ID and the component props. See this example on rendering the `my-button` component with the label <em>Click Me</em> using the `hook_page_top`:

```php
<?php
// hook_page_top
$page_top['cta-button'] = [
  '#type' => 'cl_component',
  '#component' => 'my-button',
  '#variant' => 'primary',
  '#context' => [
    'text' => t('Click Me'),
  ],
];
?>
```

This will take care of CSS, JS, HTML, and assets for you.

### Via Twig templates
Go to the template where you want to place your component, and `embed/include` it.

**Easy to embed**
<p>Embed your component like you always embedded your template.</p>
<p><em>You can use the familiar `include/embed` with the component's machine name</p>

**Libraries included**
<p>JS and CSS files inside the component folder will be **included** during render.</p>
<p><em>For caching reasons you can include `cl_components/all` in your theme. This library includes the CSS and JS for all the components.</em></p>

**Additional context**
<p>The templates for your components will receive **additional context** that will make your theming experience more flexible and powerful.</p>
<p><em>Learn more about the additional context in <a href="https://git.drupalcode.org/project/cl_components/-/blob/1.x/docs/writing-components.md#twig-templates">the documentation</a>.</em></p>

#### With the traditional syntax: `include` or `embed`
Just provide the machine name of the template of the component. CL Components add the CSS & JS if needed.

**Example of how to embed a button:**
```
{% include "my-button--primary" with {
  text: 'Click Me',
  iconType: 'external'
} %}
```

**Example of how to render a block in a card component:**
```
{% embed 'my-card' with {
  header: label
} %}
  {% block card_body %}
    {{ content.field_media_image }}
    <div class="summary">
      {{ content|without('field_media_image') }}
    </div>
    {% include 'my-button' with { text: 'Like', iconType: 'like' } %}
  {% endblock %}
{% endembed %}
```

![Embedding a component](https://www.drupal.org/files/ksnip_20220418-005414.png)
