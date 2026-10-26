<div class="modal fade" id="edit" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="row">
                <div class="col-md-12">
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>

                    </div>
                    @endif
                    <form id="expense_form" action="{{route('expense.update')}}" method="post">
                        @csrf()
                        <div class="modal-body">
                            <input type="hidden" id="id" name="id">

                            <div class="row mb-3">
                                <label for="date2" class="col-md-4 col-form-label text-md-end">{{ __('Date') }}<span style="color: red;">*</span></label>
                                <div class="col-md-8">
                                    <input type="date" id="date2" name="expense_date" class="form-control"
                                        max="{{ now('Africa/Dar_es_Salaam')->toDateString() }}" required>
                                </div>
                            </div>
                   
                            <div class="row mb-3">
                                <label for="expense_amount" class="col-md-4 col-form-label text-md-end">{{ __('Expense
                                    Amount') }}<span style="color: red;">*</span></label>
                                <div class="col-md-8">
                                    <input type="text" id="expense_amount2" class="form-control"
                                        onkeypress="return isNumberKey(event,this)" name="expense_amount" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="expense_category"
                                    class="col-md-4 col-form-label text-md-end">{{ __('Expense Category') }}<span
                                        style="color: red;">*</span></label>
                                <div class="col-md-8">
                                    <div id="category" style="border: 2px solid white; border-radius: 6px;">
                                        <select id="expense_category2" name="expense_category" class="form-select">
                                            <option selected="true" value="0" disabled="disabled">Select category
                                            </option>
                                            @foreach($expense_category as $x)
                                            <option value="{{$x->id}}">{{$x->name}}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="expense_description"
                                    class="col-md-4 col-form-label text-md-end">{{ __('Expense Description') }}</label>
                                <div class="col-md-8">
                                    <textarea class="form-control" id="expense_description2" name="expense_description" rows="3" required></textarea>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>