import {
  Command,
} from 'ckeditor5/src/core';

import { findAttributeRange } from 'ckeditor5/src/typing';
import { toMap } from 'ckeditor5/src/utils';
import getRangeText from './utils.js';

export default class Glossarycommand extends Command {
  refresh() {
    const model = this.editor.model;
    const selection = model.document.selection;
    const selectedElement = selection.getSelectedElement();

    // The command is enabled when the "glossary" element can be inserted at the current selection.
    this.isEnabled = model.schema.checkChild(selection.focus.parent, 'glossary');

    // If a glossary element is selected, get its attributes and store them in the command's value.
    if (selectedElement && selectedElement.is('element', 'glossary')) {
      this.value = Object.fromEntries(selectedElement.getAttributes());
    } else {
      this.value = null;
    }
  }

  execute(value) {
    const model = this.editor.model;

    model.change(writer => {
      // If there is a selection, remove the contents and insert the new glossary element.
      const selection = model.document.selection;
      const range = selection.getFirstRange();

      // Create the glossary element with all its attributes.
      const glossaryElement = writer.createElement('glossary', {
        ...value,
        glossaryDisplayText: value.glossaryDisplayText,
      });

      // Insert the new element into the model.
      model.insertContent(glossaryElement, range);

      // Put the selection at the end of the inserted element.
      writer.setSelection(writer.createPositionAfter(glossaryElement));
    });
  }
}
