# Common errors

## "No current controller available" during reindex

This error usually occurrs when you have code that is attempting to access a request controller during the reindex process. For example, you have code that runs `Controller::curr()`. The reindexing process does not have access to any sort of request controller (because no request is being made), and so these calls will fail.

Commonly seen when Elemental User Forms is included in the project. Explanation:

1. `ElementalPageExtension` includes a [method](https://github.com/silverstripe/silverstripe-elemental/blob/5/src/Extensions/ElementalPageExtension.php#L56) `getElementsForSearch()` (which most of us are probably using to index Elemental content)
2. `getElementsForSearch()` calls `BaseElement::getContentForSearchIndex()`, which in turn calls `forTemplate()` [here](https://github.com/silverstripe/silverstripe-elemental/blob/5/src/Models/BaseElement.php#L539)
3. `forTemplate()` will render the element as required by the silverstripe template, by default, this includes a call to `$Form` [here](https://github.com/dnadesign/silverstripe-elemental-userforms/blob/4/templates/DNADesign/ElementalUserForms/Model/ElementForm.ss#L6)
4. The call to `$Form` will trigger [this](https://github.com/dnadesign/silverstripe-elemental-userforms/blob/4/src/Model/ElementForm.php#L51) `Form()` method on the model, and this method includes a call to `Controller::curr()` 

**Result:** Because there is no request being made during the reindex process, there is no request controller available, and so this call fails.

**Reccommendations:**

* Wrap calls to `Controller::curr()` in a try/catch
* When defining your search [Configuration](configuration.md), do not include methods or content that attempt to access `Controller:curr()`
* In the case of Elemental, [Usage](https://github.com/silverstripe/silverstripe-elemental/blob/5/docs/en/03_searching-blocks.md#usage) examples are provided. One option would be to specify certain classes of element to be excluded from search by using the configuration `search_indexable: false`
