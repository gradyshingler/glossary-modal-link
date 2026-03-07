/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */

import {
  ButtonView,
  View,
  LabeledFieldView,
  createLabeledInputText,
  submitHandler,
  FocusCycler
} from 'ckeditor5/src/ui'

import {
  FocusTracker,
  KeystrokeHandler
} from 'ckeditor5/src/utils';

import {
  IconCheck,
  IconCancel,
} from '@ckeditor/ckeditor5-icons'

export default class FormView extends View {
  constructor( locale ) {
    super( locale );

    this.focusTracker = new FocusTracker();
    this.keystrokes = new KeystrokeHandler();

    const headerView = new View();
    headerView.setTemplate({
        tag: 'div',
        attributes: {
            class: [ 'ck', 'ck-form__header' ]
        },
        children: [
            {
                tag: 'h2',
                attributes: {
                    class: [ 'ck', 'ck-form__header__label' ]
                },
                children: [ 'Glossary Link' ]
            }
        ]
    });


    this.titleInputView = this._createInput( 'Add Link Title' );
    this.entitySearchInputView = this._createInput( 'Search Glossary Term' );

    this.saveButtonView = this._createButton( 'Save', IconCheck, 'ck-button-save' );
    this.saveButtonView.type = 'submit';
    this.cancelButtonView = this._createButton( 'Cancel', IconCancel, 'ck-button-cancel' );

    // Delegate ButtonView#execute to FormView#cancel.
    this.cancelButtonView.delegate( 'execute' ).to( this, 'cancel' );

    const entitySearchInputRow = new View();
    entitySearchInputRow.setTemplate({
      tag: 'div',
      attributes: {
        class: [ 'ck', 'ck-form__row', 'ck-form__row_large-top-padding' ]
      },
      children: [ this.entitySearchInputView ]
    });

    const titleInputRow = new View();
    titleInputRow.setTemplate({
        tag: 'div',
        attributes: {
            class: [ 'ck', 'ck-form__row', 'ck-form__row_large-top-padding' ]
        },
        children: [ this.titleInputView ]
    });

    const buttonsRow = new View();
    buttonsRow.setTemplate({
        tag: 'div',
        attributes: {
            class: [ 'ck', 'ck-form__row', 'ck-form__row_with-buttons' ]
        },
        children: [ this.saveButtonView, this.cancelButtonView ]
    });

    this.childViews = this.createCollection( [
      headerView,
      entitySearchInputRow,
      titleInputRow,
      buttonsRow
    ] );

    this._focusCycler = new FocusCycler( {
      focusables: this.createCollection( [
        this.entitySearchInputView,
        this.titleInputView,
        this.saveButtonView,
        this.cancelButtonView
      ] ),
      focusTracker: this.focusTracker,
      keystrokeHandler: this.keystrokes,
      actions: {
        // Navigate form fields backwards using the Shift + Tab keystroke.
        focusPrevious: 'shift + tab',

        // Navigate form fields forwards using the Tab key.
        focusNext: 'tab'
      }
    } );

    this.setTemplate( {
      tag: 'form',
      attributes: {
        class: [ 'ck', 'ck-glossary-search-form'],
        tabindex: '-1'
      },
      children: this.childViews
    } );
  }

  render() {
    super.render();

    submitHandler( {
      view: this
    } );

    const focusables = this._focusCycler.focusables;
    focusables.forEach( view => {
      // Register the view in the focus tracker.
      this.focusTracker.add( view.element );
    } );

    // Start listening for the keystrokes coming from #element.
    this.keystrokes.listenTo( this.element );

    // Initialize Linkit autocomplete on taxonomyTermInputView
    if (window.initializeAutocomplete) {
      const inputEl = this.entitySearchInputView.fieldView.element;
      window.initializeAutocomplete(inputEl, {
        autocompleteUrl: '/linkit/autocomplete/glossary_profile',
        selectHandler: (event, ui) => {
          // Store all glossary data attributes from the selected item
          this.selectedGlossaryData = {
            glossaryId: ui.item.label,
            glossaryLabel: ui.item.label,
            glossaryUuid: ui.item.entity_uuid || '',
            glossaryType: ui.item.entity_type_id || '',
            glossaryPath: ui.item.path || '',
            glossarySubstitutionId: ui.item.substitution_id || ''
          };
          // If the title field is empty, pre-populate it with the selected term.
          if ( !this.titleInputView.fieldView.element.value ) {
            this.titleInputView.fieldView.value = ui.item.label;
            this.titleInputView.isEmpty = false;
          }
        },
        closeHandler: () => {},
        openHandler: () => {}
      });
    } else {
      console.error('window.initializeAutocomplete is not available!');
    }
  }

  destroy() {
    super.destroy();

    this.focusTracker.destroy();
    this.keystrokes.destroy();
  }

  focus() {
    // If the glossaryId text field is enabled, focus it straight away to allow the user to type.
    if ( this.entitySearchInputView.isEnabled ) {
      this.entitySearchInputView.focus();
    } else {
      this.titleInputView.focus();
    }
  }

  _createInput( label ) {
    const labeledInput = new LabeledFieldView( this.locale, createLabeledInputText );

    labeledInput.label = label;

    return labeledInput;
  }

  _createButton( label, icon, className ) {
    const button = new ButtonView();

    button.set( {
      label,
      icon,
      tooltip: true,
      class: className
    } );

    return button;
  }
}
