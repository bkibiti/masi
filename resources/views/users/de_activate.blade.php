<div class="modal fade" id="disableUser" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
          <div class="modal-content">

          <form action="{{route('users.deactivate')}}" method="POST">
              @csrf

              <div class="modal-body">
                <p class="text-center" id = "prompt_message"> </p>
                <input type="hidden" id="userid" name="userid" value="">
                <input type="hidden" id="status" name="status" value="">

              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                <button type="submit" class="btn btn-warning btn-sm">Yes</button>
              </div>
            </form>

          </div>
        </div>
 </div>
