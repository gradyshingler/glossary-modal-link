/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */

import { ButtonView, ContextualBalloon, clickOutsideHandler } from 'ckeditor5/src/ui';
import { Plugin } from 'ckeditor5/src/core';
import FormView from './glossaryview';
import getRangeText from './utils.js';

export default class Glossaryui extends Plugin {
  static get requires() {
    return [ ContextualBalloon ];
  }

  init() {
    const editor = this.editor;

    // Create the balloon and the form view.
    this._balloon = this.editor.plugins.get( ContextualBalloon );
    this.formView = this._createFormView();

    editor.ui.componentFactory.add( 'glossary', () => {
      const button = new ButtonView();

      button.label = 'Glossary';
      button.tooltip = true;
      button.withText = true;

      // Show the UI on button click.
      this.listenTo( button, 'execute', () => {
        this._showUI();
      } );

      return button;
    } );

    this._enableLinkAutocomplete();

    // Show the UI on click.
    this.listenTo( this.editor.editing.view.document, 'click', ( evt, data ) => {
      const target = data.target;

      // Check if the click is on a glossary widget.
      if (target.is('element', 'span') && target.hasClass('glossary-btn')) {
        // The click is on the widget wrapper, we need to find the model element.
        const modelElement = this.editor.editing.mapper.toModelElement(target);
        if (modelElement && modelElement.is('element', 'glossary')) {
          this._showUI();
        }
        return;
      }

      // Check if the click is on the text inside the widget.
      if (target.is('text') && target.parent.is('element', 'span') && target.parent.hasClass('glossary-btn')) {
        const modelElement = this.editor.editing.mapper.toModelElement(target.parent);
        if (modelElement && modelElement.is('element', 'glossary')) {
          this._showUI();
        }
      }
    });

    // Add a keystroke listener for CMD+G to show the UI.
    editor.keystrokes.set( 'Cmd+G', ( key, stop ) => {
      this._showUI();
      stop();
    } );
  }

  _createFormView() {
    const editor = this.editor;
    const formView = new FormView( editor.locale );

    // Execute the command after clicking the "Save" button.
    this.listenTo( formView, 'submit', () => {
      const commandValue = editor.commands.get('addGlossary').value || {};
      let displayText = formView.titleInputView.fieldView.element.value;

      // Start with the existing data, if any.
      let value = { ...commandValue };

      // If there's new data from the autocomplete, merge it in.
      if (formView.selectedGlossaryData) {
        value = { ...value, ...formView.selectedGlossaryData };
      }

      // If there's no autocomplete data and no existing data,
      // fall back to the search term from the input.
      if (!formView.selectedGlossaryData && !commandValue.glossaryId) {
          const searchTerm = formView.entitySearchInputView.fieldView.element.value;
          value.glossaryId = searchTerm;
          value.glossaryLabel = searchTerm;
      }

      // If the display text is empty, default to the glossary label from autocomplete or the search term.
      if (!displayText) {
        displayText = (formView.selectedGlossaryData ? formView.selectedGlossaryData.glossaryLabel : value.glossaryId) || '';
      }

      // Always update the display text and the label.
      value.glossaryDisplayText = displayText;
      value.glossaryLabel = displayText;

      editor.execute('addGlossary', value);
      this._hideUI();
    } );

    // Hide the form view after clicking the "Cancel" button.
    this.listenTo( formView, 'cancel', () => {
      this._hideUI();
    } );

    // Hide the form view when clicking outside the balloon.
    clickOutsideHandler( {
      emitter: formView,
      activator: () => this._balloon.visibleView === formView,
      contextElements: [ this._balloon.view.element ],
      callback: () => this._hideUI()
    } );

    formView.keystrokes.set( 'Esc', ( data, cancel ) => {
      this._hideUI();
      cancel();
    } );

    return formView;
  }

  _showUI() {
    const selection = this.editor.model.document.selection;
    const commandValue = this.editor.commands.get( 'addGlossary' ).value;

    this._balloon.add( {
      view: this.formView,
      position: this._getBalloonPositionData()
    } );

    // Enable the input when the selection is collapsed or when editing an existing element.
    this.formView.titleInputView.isEnabled = true;

    if (commandValue) {
      this.formView.entitySearchInputView.fieldView.value = commandValue.glossaryId || commandValue.glossaryLabel || '';
      this.formView.titleInputView.fieldView.value = commandValue.glossaryDisplayText || '';
    } else {
      this.formView.entitySearchInputView.fieldView.value = '';
      this.formView.titleInputView.fieldView.value = getRangeText( selection.getFirstRange() );
    }

    this.formView.focus();
  }

  _hideUI() {
    // Clear the input field values and reset the form.
    this.formView.titleInputView.fieldView.value = '';
    this.formView.entitySearchInputView.fieldView.value = '';
    this.formView.selectedGlossaryData = null;
    this.formView.element.reset();

    this._balloon.remove( this.formView );

    // Focus the editing view after inserting the glossaryId so the user can start typing the content
    // right away and keep the editor focused.
    this.editor.editing.view.focus();
  }

  _getBalloonPositionData() {
    const view = this.editor.editing.view;
    const viewDocument = view.document;
    // Set a target position by converting view selection range to DOM
    return {
      target: () => view.domConverter.viewRangeToDom( viewDocument.selection.getFirstRange() )
    };
  }

  _enableLinkAutocomplete() {
    // No entityIDView logic needed anymore
    // Only extend template and add autocomplete class to entitySearchInputView
    const editor = this.editor;
    const linkFormView = this.formView;
    linkFormView.extendTemplate({
      attributes: {
        class: ['ck-vertical-form', 'ck-link-form_layout-vertical'],
      },
    });
    linkFormView.entitySearchInputView.fieldView.template.attributes.class.push('form-linkit-autocomplete');
    editor.plugins
      .get('ContextualBalloon')
      .on('set:visibleView', () => {
        // Handler intentionally left empty
      });
  }
}
