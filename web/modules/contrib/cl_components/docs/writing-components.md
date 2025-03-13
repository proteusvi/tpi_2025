# Storybook Components

## Assumptions

- Component templates use the file extension `.twig` (instead of `.html.twig`) -
  this prevents Drupal from picking up templates and rendering them when they
  share a name with a template Drupal already knows about
- Components are structured like:

```console
[PROJECT_ROOT]/...
    |- ... organize your components as you see fit
        |- my-component-machine-name
            |- README.md (documentation for component)
            |- thumbnail.png (thumbnail for component selectors)
            |- my-component-machine-name.twig (required)
            |- my-component.component.yml (required)
            |- assets
                |- static
                    (assets that won't be processed and need to be copied to dist folder)
                    |- ...
                |- css
                    |- some-styles.scss
                    |- some-more-styles.css
                |- js
                    |- some-javascript.js
                    |- other-javascript.js
                |- img
                    |- img1.png
```

## Component Metadata

The component metadata JSON file is used to provide information about the
component that needs to be consistent in Drupal and in Storybook.

Extend this file if you need to provide information for a component that needs
to be available in Drupal and in your stories.

An example could be this. Imagine you have several color schemes, and you want
editors to choose from the supported schemes. Some components support setting
the color palette (like your navigation), but others do not support palettes
(like the image component). The metadata file should follow the format described
by [this schema](https://git.drupalcode.org/project/cl_components/-/raw/1.x/src/metadata.schema.json)
.

You could write a `my-image.component.yml` like:

```yaml
$schema: https://git.drupalcode.org/project/cl_components/-/raw/1.x/src/metadata.schema.json
machineName: my-image
name: Image
status: BETA
componentType: molecule
schemas:
  props:
    type: object
    required:
      - caption
    properties:
      caption:
        type: string
        title: Caption
        description: The caption for the image
        examples:
          - A bird eating grass seeds.
```

See how this component is not to be used directly in Drupal, but in other
components, we can flag that with `"componentType": "utility"`. Then our custom
module can remove it from the available components in Drupal.

## Twig templates

The folder that contains the `component-id.component.yml` is the _component
folder_. For a component to be valid there needs to be at least
one `component-id.twig` file. That is considered to be the main template for the
component. Other templates are allowed for component variants. They need to
follow the naming convention `component-id--variant-name.twig`. Component
templates have several variables available to them:

- `clAttributes`: an object with HTML attributes meant for the root of your
  component.
  Check [this example](https://git.drupalcode.org/project/cl_components/-/blob/1.x/examples/components/my-button/my-button--primary.twig)
  component to see it in action.
- `clMeta`: an object containing the component metadata:
  - `path`: the path to the component
  - `uri`: the URI pointing to the component folder. Useful to link to static
    assets.
  - `machineName`: the component machine name.
  - `status`: the component status.
  - `componentType`: the component type (utility or module).
  - `name`: the human-readable name for the component.
  - `group`: the component group.
  - `variants`: available variants for the component.
  - `libraryDependencies`: additional drupal libraries necessary for the
    component.

## Stylesheets and scripts

The component discovery finds JS and CSS files recursively inside each component
folder. Then the module creates a dynamic library with the name
`cl_components/<component-id>`. This library is included with the component
template whenever the component is rendered using one of the methods described
in the project page.

### Compiled assets

If you want to use modern JavaScrip features, or need to compile SCSS (or use
PostCSS) you can use your preferred tools. Then make sure to point the discovery
service to the destination folder.

For instance, imagine you import a package with Tailwind components. This
package ends up in `<project-root>/vendor/my-project/tailwind-drupal-components`
. You can make your compilation pipeline (Babel, Webpack, or whatever you need)
to compile those components
into `<project-root>/web/sites/default/files/compiled-components/my-component/`.
Then you will need to add `sites/default/files/compiled-components` in the
configuration for component inspection paths.

Remember that your pipeline should also copy the rest of the files, otherwise
the resulting component folder would be invalid.
