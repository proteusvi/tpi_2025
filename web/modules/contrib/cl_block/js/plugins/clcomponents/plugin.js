/**
 * @file
 * Drupal CL component embed plugin.
 */

(function ($, Drupal, CKEDITOR) {

  "use strict";

  function getFocusedWidget(editor) {
    var widget = editor.widgets.focused;

    if (widget && widget.name === 'clcomponent') {
      return widget;
    }

    return null;
  }

  var pluginDefinition = {
    // This plugin requires the Widgets System defined in the 'widget' plugin.
    requires: 'widget',

    // The plugin initialization logic goes inside this method.
    beforeInit: function (editor) {
      // Configure CKEditor DTD for custom cl-component element.
      // @see https://www.drupal.org/node/2448449#comment-9717735
      var dtd = CKEDITOR.dtd, tagName;
      dtd['cl-component'] = {'#': 1};
      // Register cl-component element as allowed child, in each tag that can
      // contain a div element.
      for (tagName in dtd) {
        if (dtd[tagName].div) {
          dtd[tagName]['cl-component'] = 1;
        }
      }

      // Generic command for adding/editing components of all types.
      editor.addCommand('editclcomponent', {
        allowedContent: 'cl-component[data-component-id,data-component-variant,data-component-settings]',
        requiredContent: 'cl-component[data-component-id,data-component-settings]',
        modes: { wysiwyg : 1 },
        canUndo: true,
        exec: function (editor, data) {
          data = data || {};

          var existingElement = getSelectedEmbeddedElement(editor);
          var existingWidget = (existingElement) ? editor.widgets.getByElement(existingElement, true) : null;

          var existingValues = {};

          if (existingWidget) {
            existingValues = existingWidget.data.attributes;
          }

          var embed_button_id = data.id ? data.id : existingValues['data-embed-button'];
          var title = existingElement
            ? `${editor.config.ClComponent_dialogTitleEdit} ${existingElement.$.querySelector('cl-component').getAttribute('component-name')}`
            : editor.config.ClComponent_dialogTitleAdd;
          var dialogSettings = {
            title: title,
            dialogClass: 'cl-block-select-dialog',
            resizable: false,
          };

          var saveCallback = function (values) {
            editor.fire('saveSnapshot');
            if (!existingElement) {
              var element = editor.document.createElement('cl-component');
              var attributes = values.attributes;
              for (var key in attributes) {
                element.setAttribute(key, attributes[key]);
              }
              editor.insertHtml(element.getOuterHtml());
            }
            else {
              existingWidget.setData({ attributes: values.attributes });
            }
            editor.fire('saveSnapshot');
          };

          // Open the PDS embed dialog for corresponding EmbedButton.
          Drupal.ckeditor.openDialog(editor, Drupal.url('cl-block/dialog/' + editor.config.drupal.format + '/' + embed_button_id), existingValues, saveCallback, dialogSettings);
        }
      });

      // Register the PDS embed widget.
      editor.widgets.add('clcomponent', {
        // Minimum HTML which is required by this widget to work.
        allowedContent: 'cl-component[data-component-id,data-component-variant,data-component-settings]',
        requiredContent: 'cl-component[data-component-id,data-component-settings]',

        // Simply recognize the element as our own. The inner markup if fetched
        // and inserted the init() callback, since it requires the actual DOM
        // element.
        upcast: function (element, data) {
          var attributes = element.attributes;
          if (element.name !== 'cl-component' || attributes['data-component-id'] === undefined || attributes['data-component-settings'] === undefined) {
            return;
          }
          data.attributes = CKEDITOR.tools.copy(attributes);
          // Generate an ID for the element, so that we can use the Ajax
          // framework.
          data.attributes.id = generateEmbedId();
          return element;
        },

        init: function () {
          /** @type {CKEDITOR.dom.element} */
          var element = this.element;

          // See https://www.drupal.org/node/2544018.
          if (element.hasAttribute('data-embed-button')) {
            var buttonId = element.getAttribute('data-embed-button');
            if (editor.config.ClComponent_buttons[buttonId]) {
              var button = editor.config.ClComponent_buttons[buttonId];
              this.wrapper.data('cke-display-name', Drupal.t('Embedded @buttonLabel', {'@buttonLabel': button.label}));
            }
          }
        },

        data: function (event) {
          if (this._previewNeedsServersideUpdate()) {
            editor.fire('lockSnapshot');
            this._loadPreview(function (widget) {
              editor.fire('unlockSnapshot');
              editor.fire('saveSnapshot');
            });
          }

          // Allow cl_block.editor.css to respond to changes (for example in alignment).
          this.element.setAttributes(this.data.attributes);

          // Track the previous state, to allow for smarter decisions.
          this.oldData = CKEDITOR.tools.clone(this.data);
        },

        // Downcast the element.
        downcast: function () {
          // Only keep the wrapping element.
          //element.setHtml('');
          // Remove the auto-generated ID.
          //delete element.attributes.id;
          //return element;
          var downcastElement = new CKEDITOR.htmlParser.element('cl-component', this.data.attributes);
          return downcastElement;
        },

        _previewNeedsServersideUpdate: function () {
          // When the widget is first loading, it of course needs to still get a preview!
          if (!this.ready) {
            return true;
          }

          return this._hashData(this.oldData) !== this._hashData(this.data);
        },

        /**
         * Computes a hash of the data that can only be previewed by the server.
         */
        _hashData: function (data) {
          var dataToHash = CKEDITOR.tools.clone(data);
          return JSON.stringify(dataToHash);
        },

        /**
         * Loads an embed preview, calls a callback after insertion.
         *
         * @param {function} callback
         *   A callback function that will be called after the preview has loaded, and receives the widget instance.
         */
        _loadPreview: function (callback) {
          // Use the Ajax framework to fetch the HTML, so that we can retrieve
          // out-of-band assets (JS, CSS...).
          var widget = this;

          jQuery.get({
            url: Drupal.url('embed/preview/' + editor.config.drupal.format),
            data: {
              'text': this.downcast().getOuterHtml(),
            },
            dataType: 'html',
            headers: {
              'X-Drupal-EmbedPreview-CSRF-Token': editor.config.drupalEmbed_previewCsrfToken
            },
            success: (previewHtml) => {
              this.element.setHtml(previewHtml);
              callback(this);
            },
          });
        }

      });

      editor.widgets.on('instanceCreated', function (event) {
        var widget = event.data;

        if (widget.name !== 'clcomponent') {
          return;
        }

        widget.on('edit', function (event) {
          event.cancel();
          // @see https://www.drupal.org/node/2544018
          if (isEditableElementWidget(editor, event.sender.wrapper)) {
            editor.execCommand('editclcomponent');
          }
        });
      });


      // Register the toolbar buttons.
      if (editor.ui.addButton) {
        for (var key in editor.config.ClComponent_buttons) {
          var button = editor.config.ClComponent_buttons[key];
          editor.ui.addButton(button.id, {
            label: button.label,
            data: button,
            allowedContent: 'cl-component[!data-component-id,!data-component-settings,!data-embed-button]',
            click: function(editor) {
              editor.execCommand('editclcomponent', this.data);
            },
            icon: button.image,
            modes: {wysiwyg: 1, source: 0}
          });
        }
      }

      // Register context menu option for editing widget.
      if (editor.contextMenu) {
        editor.addMenuGroup('clcomponent');
        editor.addMenuItem('clcomponent', {
          label: Drupal.t('Edit embedded component'),
          command: 'editclcomponent',
          group: 'clcomponent'
        });

        editor.contextMenu.addListener(function(element) {
          if (isEmbeddedElementWidget(editor, element)) {
            return { clcomponent: CKEDITOR.TRISTATE_OFF };
          }
        });
      }

      // Execute widget editing action on double click.
      editor.on('doubleclick', function (evt) {
        var element = getSelectedEmbeddedElement(editor) || evt.data.element;
        if (isEmbeddedElementWidget(editor, element)) {
          editor.execCommand('editclcomponent');
        }
      });
    }
  };
  CKEDITOR.plugins.add('clcomponent', pluginDefinition);

  /**
   * Get the surrounding clcomponent widget element.
   *
   * @param {CKEDITOR.editor} editor
   */
  function getSelectedEmbeddedElement(editor) {
    var selection = editor.getSelection();
    var selectedElement = selection.getSelectedElement();
    if (isEmbeddedElementWidget(editor, selectedElement)) {
      return selectedElement;
    }

    return null;
  }

  /**
   * Returns whether or not the given element is a clcomponent widget.
   *
   * @param {CKEDITOR.editor} editor
   * @param {CKEDITOR.htmlParser.element} element
   */
  function isEmbeddedElementWidget (editor, element) {
    var widget = editor.widgets.getByElement(element, true);
    return widget && widget.name === 'clcomponent';
  }

  /**
   * Checks if the given element is an editable clcomponent widget.
   *
   * @param {CKEDITOR.editor} editor
   * @param {CKEDITOR.htmlParser.element} element
   */
  function isEditableElementWidget (editor, element) {
    var widget = editor.widgets.getByElement(element, true);
    if (!widget || widget.name !== 'clcomponent') {
      return false;
    }

    var button = element.$.firstChild.getAttribute('data-embed-button');
    if (!button) {
      // If there was no data-embed-button attribute, not editable.
      return false;
    }

    // The button itself must be valid.
    return editor.config.ClComponent_buttons.hasOwnProperty(button);
  }

  /**
   * Generates unique HTML IDs for the widgets.
   *
   * @returns {string}
   */
  function generateEmbedId() {
    if (typeof generateEmbedId.counter == 'undefined') {
      generateEmbedId.counter = 0;
    }
    return 'cl-component-embed-' + generateEmbedId.counter++;
  }


})(jQuery, Drupal, CKEDITOR);
