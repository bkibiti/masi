<div class="modal fade" id="edit" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Update Expense Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{route('expense-categories.update','id')}}" method="post">
                @csrf()
                @method("PUT")

                <div class="modal-body">
                    <div class="row mb-3">
                        <label for="code" class="col-md-4 col-form-label text-md-end">Category Name <font color="red">*</font></label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="name_edit" name="name"
                                   aria-describedby="emailHelp" maxlength="40" required="true">
                        </div>
                    </div>

                    <input type="hidden" name="id" id="id_edit">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
