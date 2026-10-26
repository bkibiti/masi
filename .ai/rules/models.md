---
paths:
  - 'app/Models/**'
---

# Models

## Persist by assigning attributes then save()
Create and update records with `$model = new Model; $model->field = $request->field; ... $model->save();`. Models do not declare `$fillable`, so do not use `Model::create([...])` or `->update([...])`.

## Always declare the table on models
The schema predates the models and table names don't follow Laravel conventions. Always set `protected $table`, and set `public $timestamps = false;` when the table has no `created_at`/`updated_at`. Check columns with the `database-schema` tool.
