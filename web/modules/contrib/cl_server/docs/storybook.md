# Add Storybook to your Drupal Site

There are two parts you need to configure to set up Storybook in Drupal. This is a one time setup for your Drupal site. After this, all the Storybook documentation will apply to your project. You can add addons, write stories, etc. provided they are compatible with Storybook's server framework.

## The Drupal Part

If you don't have a Drupal site install one from scratch. Install Drupal 10.1 or
later to use SDC provided by Drupal core.

If you haven't already, change the minimum stability to `dev` inside `composer.json`. This is so we can install the Drupal modules that don't have, yet, a stable release.

Next we need to install the Drupal module.

```console
composer require "drupal/cl_server:^2.0.0@beta"
drush pm:enable --yes cl_server
```

Next we need to enable and update `development.services.yml`. This tutorial assumes you start your site from scratch. If you already have enabled `settings.local.php` and development services, you can skip this step.

```console
# Enable local settings. Instructions are at the bottom of the file.
vim web/sites/default/settings.php

cp web/sites/example.settings.local.php web/sites/default/settings.local.php
vim web/sites/default/settings.local.php
# Disable caches during development. This allows finding new components without clearing caches.
$settings['cache']['bins']['discovery'] = 'cache.backend.null';

# ...and add Twig and Cors settings below.
vim web/sites/development.services.yml
```

In `development.services.yml` you want to add some configuration for Twig, so you don't need to clear caches so often. This is not needed for the Storybook integration, but it will make things easier when you need to move components to your Drupal templates.

You also need to enable CORS, so the Storybook application can talk to your Drupal site. You want this CORS configuration to be in `development.services.yml` so it does not get changed in your production environment. If you mean to use _CL Server_ in production, make sure to restrict CORS as much as possible. Remember _CL Server_ development mode **SHOULD** be disabled in production.

The configuration you want looks like this:

```yaml
parameters:
  # ...
  twig.config:
    debug: true
    cache: false
  # Remember to disable development mode in production!
  cl_server.development: true
  cors.config:
    enabled: true
    allowedHeaders: ['*']
    allowedMethods: ['*']
    allowedOrigins: ['*']
    exposedHeaders: false
    maxAge: false
    supportsCredentials: true
services:
  # ...
```

Clear caches to have the dependency container pick up on the new config:

```console
drush cache:rebuild
```

Note that you might still experience CORS issues unrelated to Drupal such as Apache/NGINX configuration files, reverse proxies, etc. If you were to experience any issue, think about any additional layer on your infrastructure that might alter CORS headers, requests, etc...

For example, DDEV users might need to tweak the nginx file to manually add the header to support `woff/woff2` assets (see next section).

### Prepare ddev for running the Storybook application
If you are using ddev for you local environment you will need to expose some ports to connect to Storybook. You can do so by adapting the following snippet in your `.ddev/config.yaml`:

<details><summary><strong>See ddev configuration</strong></summary>

```yaml
###############################################################################
# Customizations
###############################################################################
nodejs_version: "18"
webimage_extra_packages:
  - pkg-config
  - libpixman-1-dev
  - libcairo2-dev
  - libpango1.0-dev
  - make
web_extra_exposed_ports:
  - name: storybook
    container_port: 6006
    http_port: 6007
    https_port: 6006
web_extra_daemons:
  - name: node.js
    command: "tail -F package.json > /dev/null"
    directory: /var/www/html
hooks:
  post-start:
    - exec: echo '================================================================================='
    - exec: echo '                                  NOTICE'
    - exec: echo '================================================================================='
    - exec: echo 'The node.js container is ready. You can start storybook by typing:'
    - exec: echo 'ddev yarn storybook'
    - exec: echo
    - exec: echo 'By default it will be available at https://change-me.ddev.site:6006'
    - exec: echo "Use ddev describe to confirm if this doesn't work."
    - exec: echo 'Check the status of startup by running "ddev logs --follow --time"'
    - exec: echo '================================================================================='

###############################################################################
# End of customizations
###############################################################################
```

</details>

<details><summary><strong>Manually support missing assets (fonts, etc)</strong></summary>

Some users have reported that even with CORS enabled on Drupal, font assets (i.e. `woff/woff2` fonts) won't be served due to CORS.

As a workaround, you can take control of the `nginx-site.conf` file and tweak it. Just do the following:

1. Remove the `#ddev-generated` line (usually, the third line) on `.ddev/nginx_full/nginx-site.conf`. This will allow you to override DDEV defaults, see more info [here](https://ddev.readthedocs.io/en/latest/users/extend/customization-extendibility/#custom-nginx-configuration).
2. Locate this line and manually add the CORS header:
```yml
  # Media: images, icons, video, audio, HTC
  location ~* \.(png|jpg|jpeg|gif|ico|svg|woff|woff2)$ { # <--- Add the missing extensions
    add_header Access-Control-Allow-Origin *; # <--- Add the CORS header
    try_files $uri @rewrite;
    expires max;
    log_not_found off;
  }
```
3. Run `ddev restart`

</details>

## The Storybook Part

Now it's time to install Storybook. For the purposes of this documentation we will use `yarn`, but you can use any other Node package manager (npm, pnpm, etc).

We will use the latest Storybook version (7.x), which recommends to use Node.js [equal or greather than 16](https://github.com/storybookjs/storybook/blob/next/MIGRATION.md#dropped-support-for-node-15-and-below).

Docs for the Drupal addon are at: https://storybook.js.org/addons/@lullabot/storybook-drupal-addon

### 🌴 Add Storybook to your Drupal repo

First of all, check your yarn version (`yarn version`) as there are big differences between yarn "clasic" (1.x) and current versions (2.x and up).

From the root of your repo, choose your path and do the following:

<details><summary><strong>Using yarn clasic (1.x)</strong></summary>

```console
# If you are using ddev, you'll need to prefix all yarn commands as usual.

# Initialize the empty node.js project.
yarn init

# Install Storybook globally
yarn global add sb@latest;

# Init Storybook and add dependencies. You might need to stop the server (Ctrl+C) to continue.
# If you have a reason to use Webpack4 remove the --builder flag
yarn sb init --builder webpack5 --type server

# Install the Drupal addon
yarn add -D @lullabot/storybook-drupal-addon
```
</details>

**Using modern yarn (berry)**

If you use recent versions of Yarn (2.x and up), `global` packages are no longer supported (read more [here](https://yarnpkg.com/migration/guide#use-yarn-dlx-instead-of-yarn-global)), but you can run the Storybook cli directly by using `dlx` instead:

```console
# If you are using ddev, you'll need to prefix all yarn commands as usual.

# Initialize the empty node.js project.
yarn init

# Update Yarn in order to be able to use `dlx` instead of `global`
yarn set version berry

# Skip pnp for now.
echo 'nodeLinker: node-modules' >> .yarnrc.yml

# Init Storybook and add dependencies. You might need to stop the server (Ctrl+C) to continue.
# If you have a reason to use Webpack4 remove the --builder flag
yarn dlx sb@latest init --builder webpack5 --type server

# Install the Drupal addon
yarn add -D @lullabot/storybook-drupal-addon
```

When using latest yarn versions it is also advisable to update your `.gitignore` as now Yarn will store a lot of cache files and other stuff directly on your project. [See the official documentation to know what to ignore](https://yarnpkg.com/getting-started/qa#which-files-should-be-gitignored).

### 🌵 Configure Storybook
First enable the addon. Add it to the `addons` in the `.storybook/main.js`. Also
remember to point to where your stories are.

Take for example this `.storybook/main.js`.

```javascript
// .storybook/main.js
const config = {
  stories: [
    '../web/themes/**/*.stories.mdx',
    '../web/themes/**/*.stories.@(json|yml)',
    '../web/modules/**/*.stories.mdx',
    '../web/modules/**/*.stories.@(json|yml)',
  ],
  // ...jj
  addons: [
    '@storybook/addon-links',
    '@storybook/addon-essentials',
    '@lullabot/storybook-drupal-addon', // <---
  ],
  framework: {
    name: '@storybook/server-webpack5',
    options: {},
  },
  docs: {
    autodocs: 'tags',
  },
};

export default config;
```

You might want to also remove the `stories` folder that Storybook created for you on your root folder if you are not going to use it.

Then, configure the `supportedDrupalThemes` and `drupalTheme` parameters in `.storybook/preview.js`.

`supportedDrupalThemes` is an object where the keys are the machine name of the Drupal themes and the values are the plain text name of that Drupal theme you want to use. This is what will appear in the dropdown in the toolbar.

```javascript
// .storybook/preview.js
/** @type { import('@storybook/server').Preview } */
const preview = {
  globals: {
    drupalTheme: 'umami',
    supportedDrupalThemes: {
      umami: {title: 'Umami'},
      claro: {title: 'Claro'},
    },
  },
  parameters: {
    server: {
      // Replace this with your Drupal site URL, or an environment variable.
      url: 'https://change-me.ddev.site',
    },
    actions: { argTypesRegex: "^on[A-Z].*" },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
  },
  // ...
};

export default preview;
```

## Start Storybook

Start the Storybook's development server:

```console
yarn storybook
# ddev yarn storybook
```

If you are using ddev, use https://change-me.ddev.site:6006 as per the configuration above. Change `change-me` with your actual ddev project name. Note that for `http:` (without s), the port will be `6007`

Storybook will start in http://localhost:6006 and you will see a black and red screen with an error. This is because you need to set the Components config in Drupal.

You'll refresh and you will see another error. This is a 403 because you need to allow the CL Server render endpoint for the anonymous user. So you need to go to the Drupal permissions page and grant permission to the anonymous user to access the component rendering endpoint.

Now you can restart the Storybook server. Kill the `yarn storybook` process and run it again.

If you want to see the Example components to see if things are working, then you need to install the [_SDC Examples_](https://www.drupal.org/project/sdc_examples) module.

## Write your first component story

### How it works?

You will notice that, even if you have components created using SDC; nothing will appear on Storybook yet.

Storybook is primarily focused on JS frameworks (React, Vue, etc), where it tries to infer data from JSDoc comments, TypeScript annotations or from the JS source itself. Then developers can complement or extend the info.

On Drupal we are on a very different scenario as we render our components by making requests to our Drupal site via cl_server. This means Storybook can infer very little and __we need to provide most of the information manually__ via `*.yml` or `*.json` files.

### How do I write a storybook story for Drupal?

Use the following resources as a reference on how to write stories for Storybook:

- The before mentioned [_SDC Examples_](https://www.drupal.org/project/sdc_examples) module contains examples of Storybook stories in YAML format for your components.
- [This snippet](https://gitlab.com/-/snippets/2556203) of an annotated `*.stories.yml` that also indicates the related DocBlocks that will render the info on Storybook.
- The [_SDC Story Generator_](https://www.drupal.org/project/sdc_story_generator) module will provide a `drush generate` command that will convert your `*.component.yml` file into valid `*.stories.yml` file.

## How do I control the output of each component documentation?

By default, Storybook will output a minimal documentation consiting of a live rendering of each story plus a "Controls" tab that will let you modify props and see changes in real-time.

You can control / expand the documentation of each component manually if needed.

First, expand your `.storybook/main.js` to support `*.mdx` files. Please note that, for Storybook, `*.stories.mdx` and `*.mdx` files are different and have access to a different subset of tools:

```js
const config = {
  // ....
  stories: [
    "../web/themes/**/*.mdx", // <-------
    "../web/themes/**/*.stories.mdx",
    "../web/themes/**/*.stories.@(json|yml)",
    "../web/modules/**/*.mdx", // <-------
    "../web/modules/**/*.stories.mdx",
    "../web/modules/**/*.stories.@(json|yml)",
  ],
  // .....
};

```

Then, add a `mycomponent.mdx` file and (optionally) a `README.md` to your component folder.

You can use this template as a reference for the `*.mdx` file:

```jsx
{/* mycomponent.mdx */}

import {
  Meta,
  ArgTypes,
  Description,
  Primary,
  Markdown,
  Source
} from '@storybook/blocks';
import * as MyComponentStories from './mycomponent.stories.yml';
import templateSource from './alert.twig?raw';
import ReadMe from './README.md?raw';

<Meta of={ MyComponentStories } />

<Markdown>{ ReadMe }</Markdown>

## Default implementation

<Primary />

---

## Props / arguments

The component accepts the following props:

<ArgTypes />

---

## Code

<Source code={ templateSource } />
```

Notice something? MDX files are a mixture of Markdown and JSX, and you can access a plethora of [DocBlocks](https://storybook.js.org/docs/react/writing-docs/doc-blocks) to enhance your documentation page. For example, [`<ArgTypes>`](https://storybook.js.org/docs/react/api/doc-block-argtypes) will output a very useful table of all of your component props with its type and description.

Note also that you can directly `import` the README.md, the source code and your stories yml file and render them on the documentation or pass it to other MDX components. How good is that? Refer to the [official Storybook documentation](https://storybook.js.org/docs/react/writing-docs/introduction) for more info.

## How do I create extra documentation pages

You are not limited to Drupal components. You can freely create as many documentation pages as you want using `*.mdx` files.

For example, consider adding a `docs/introduction.mdx` file on your root directoy. As usual, you will need to inform your `.storybook/main.js` file

```js
const config = {
  stories: [
    "../docs/*.mdx", // <-------
  ],
};
```

You can then write your documentation and even make use of the aforementioned [DocBlocks](https://storybook.js.org/docs/react/writing-docs/doc-blocks) to enhance it with metadata and components. For example:


```jsx
import { Meta, Stories } from '@storybook/blocks';


<Meta title="Example/Introduction" />

<style>
  // You can add CSS style directly on the page
</style>

# Welcome to My Components Documentation
Lorem ipsum....

<Stories />
```

Note that the `<Meta title>` controls the hierarchy. For example, to make a sibling page you should use `<Meta title="Example/Sibling" />`, and to make a children page you should write `<Meta title="Example/Introduction/Children" />`


## How do I create a nested component?

This example shows how to create a nested component using `slots`, and use it inside Storybook as well.

### Create the component in sdc

```yml
# bdc-grid.component.yml

'$schema': 'https://git.drupalcode.org/project/drupal/-/raw/10.1.x/core/modules/sdc/src/metadata.schema.json'
name: "Bdc Grid"
status: "stable"
slots:
  grid_items:
    title: Grid items
props:
  type: object
  properties:
    cols:
      type: string
      title: Columns
      description: Grid columns
      examples:
        - "col-12 col-md-6 col-lg-4"
```

```twig
# bdc-grid.twig

<section {{ attributes.addClass('bdc-grid') }}>
  <div class="container">
    <div class="row">
      {% block grid_items %}{% endblock grid_items %}
    </div>
  </div>
</section>
```

### Use the component in Drupal template
In this case, I'm looping through a field reference (content) and rendering the items inside the grid.

```twig
# node--page--full.html.twig

{% embed 'my_new_theme:bdc-grid' with {'cols': 'col-md-4'} %}
  {% block grid_items %}
    {% for key, item in content.field_article_reference %}
      {%  if key|first != '#' %}
        <div class="{{ cols }}">
          {{ item }}
        </div>
      {% endif %}
    {% endfor %}
  {% endblock grid_items %}
{% endembed %}
```
### Use the component in Storybook

```yml
# bdc-grid.stories.yml

title: Layout/Grid
stories:
  - name: Grid
    args:
      grid_items: |
        <div class="col-12 col-md-6 col-lg-4">{% include 'my_new_theme:bdc-button' with { bdc_button_title: 'Drupal button'} %}</div>
        <div class="col-12 col-md-6 col-lg-4">{% include 'my_new_theme:bdc-button' with { bdc_button_title: 'Drupal button'} %}</div>
        <div class="col-12 col-md-6 col-lg-4">{% include 'my_new_theme:bdc-button' with { bdc_button_title: 'Drupal button'} %}</div>
```

