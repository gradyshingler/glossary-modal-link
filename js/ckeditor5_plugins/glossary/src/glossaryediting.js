/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */

import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import Glossarycommand from './glossarycommand';

export default class Glossaryediting extends Plugin {
  init() {
    // Define model and view attribute arrays
    this.attrs = [
      'glossaryId',
      'glossaryUuid',
      'glossaryType',
      'glossaryPath',
      'glossarySubstitutionId',
      'glossaryLabel',
      'glossaryDisplayText' // New attribute for user display text
    ];
    this.attrsView = [
      'data-glossary-id',
      'data-glossary-uuid',
      'data-glossary-type',
      'data-glossary-path',
      'data-glossary-substitution-id',
      'data-glossary-label',
      'data-glossary-display-text' // New data attribute for user display text
    ];
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'addGlossary', new Glossarycommand( this.editor )
    );
  }
  _defineSchema() {
    const schema = this.editor.model.schema;
    schema.register('glossary', {
      // Allow where text is allowed.
      allowWhere: '$text',
      // The glossary is an inline element.
      isInline: true,
      // The glossary is an object element (it's treated as an atomic entity).
      isObject: true,
      // Allow attributes associated with the glossary.
      allowAttributes: this.attrs,
    });
  }
  _defineConverters() {
    const conversion = this.editor.conversion;

    // Downcast: model -> view
    conversion.for('editingDowncast').elementToElement({
      model: 'glossary',
      view: (modelElement, { writer: viewWriter }) => {
        const widgetElement = this._createGlossaryView(modelElement, viewWriter);
        return toWidget(widgetElement, viewWriter, { label: 'glossary button widget' });
      },
    });

    conversion.for('dataDowncast').elementToElement({
        model: 'glossary',
        view: (modelElement, { writer: viewWriter }) => {
            return this._createGlossaryView(modelElement, viewWriter);
        }
    });


    // Upcast: view -> model
    conversion.for('upcast').elementToElement({
      view: {
        name: 'span',
        classes: ['glossary-btn', 'btn', 'btn-primary'],
        attributes: {
          'data-glossary-id': true,
        },
      },
      model: (viewElement, { writer: modelWriter }) => {
        const attributes = {};
        this.attrs.forEach((attr, i) => {
          const viewAttr = this.attrsView[i];
          if (viewElement.hasAttribute(viewAttr)) {
            attributes[attr] = viewElement.getAttribute(viewAttr);
          }
        });
        return modelWriter.createElement('glossary', attributes);
      },
    });
  }

  _createGlossaryView(modelElement, viewWriter) {
    const displayText = modelElement.getAttribute('glossaryDisplayText') || modelElement.getAttribute('glossaryLabel') || '';

    const glossaryView = viewWriter.createContainerElement(
        'span',
        {
          class: 'glossary-btn btn btn-primary',
          'data-glossary-id': modelElement.getAttribute('glossaryId'),
          'data-glossary-uuid': modelElement.getAttribute('glossaryUuid'),
          'data-glossary-type': modelElement.getAttribute('glossaryType'),
          'data-glossary-path': modelElement.getAttribute('glossaryPath'),
          'data-glossary-substitution-id': modelElement.getAttribute('glossarySubstitutionId'),
          'data-glossary-label': modelElement.getAttribute('glossaryLabel'),
          'data-glossary-display-text': displayText,
        },
        viewWriter.createText(displayText),
    );

    return glossaryView;
  }
}
