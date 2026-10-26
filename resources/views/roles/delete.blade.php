<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">

        <form action="{{route('roles.destroy')}}" method="post">
            @csrf
            @method("DELETE")

            <div class="modal-body">
                <div id="message"></div>

                <input type="hidden" name="role_id" id="role_id" value="">
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
            <button type="submit" class="btn btn-warning btn-sm">Yes</button>
            </div>
        </form>

        </div>
    </div>
</div>
    