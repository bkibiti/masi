<div class="modal fade" id="delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Expense Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('expense-categories.destroy','id') }}" method="post">
                @csrf
                @method("DELETE")

                <div class="modal-body">
                    <div id="message"></div>

                    <input type="hidden" id="id" name="id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger btn-sm">Yes</button>
                </div>
            </form>

        </div>
    </div>
</div>
