---
paths:
  - 'app/Http/**'
---

# Http

## Business logic lives in controllers
This app has no Action, Service, or Repository layer. Put logic in the controller method and query Eloquent or `DB` directly there; do not introduce new architectural layers.

## Record id comes from the request body, not route binding
Update and destroy actions read the record id from a hidden `id` form field submitted by the modal (`Model::find($request->id)`), not from route model binding. Match this in new update/destroy actions.

## Flash an alert and return back after writes
After a create/update/delete, call `session()->flash("alert-success", "...")` (or `"alert-danger"` for deletes/failures), then `return back();`. Do not use `->with()` or redirect to a named route.

## Persist by assigning attributes then save()
Create and update records with `$model = new Model; $model->field = $request->field; ... $model->save();`. Models do not declare `$fillable`, so do not use `Model::create([...])` or `->update([...])`.

## Validate inline in controllers
Validate with `$request->validate([...])` inside the controller method using pipe-string rules (`'required|string|max:50'`). Do not create Form Request classes.
