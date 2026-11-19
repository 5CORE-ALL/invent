{{--
    Searchable "Category Name" field: type to filter the existing categories and pick one; when the text
    matches nothing, the list offers "+ Add new category". The value is the plain text of the input, so
    the save code reads it exactly like the old text box.

    @param string      $inputId    id of the text input (read by the save code)
    @param string      $value      current value
    @param int|string  $currentId  id of the category being edited (0 when adding), so its own name is not "taken"
--}}
<div class="position-relative cat-combo" data-current-id="{{ $currentId ?? 0 }}">
    <input type="text" class="form-control cat-combo-input" id="{{ $inputId }}" name="category_name"
        value="{{ $value ?? '' }}" placeholder="Search or type a category..." autocomplete="off">
    <div class="cat-combo-menu list-group position-absolute w-100 shadow-sm"
        style="display:none; max-height:240px; overflow-y:auto; z-index:2055;"></div>
    <div class="cat-combo-hint form-text"></div>
</div>
