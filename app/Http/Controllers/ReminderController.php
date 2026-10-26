<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReminderController extends Controller
{
 
    public function index()
    {
   
        $reminders = Reminder::get();

        return view('reminders.index', compact("reminders"));
    }


    public function create()
    {
        //
    }

  
    public function store(Request $request)
    {
        $reminder = new Reminder;
        $reminder->name = $request->name;
        $reminder->start_date = date('Y-m-d', strtotime($request->start_date));
        $reminder->end_date = date('Y-m-d', strtotime($request->end_date));
        $reminder->days_to_remind = $request->days;
        $reminder->created_by = Auth::user()->id;
        $reminder->save();
        session()->flash("alert-success", "Reminder Added Successfully!");
        return back();
    }

    public function update(Request $request, Reminder $id)
    {
        $reminder = Reminder::find($request->id);
        $reminder->name = $request->name;
        $reminder->start_date = date('Y-m-d', strtotime($request->start_date));
        $reminder->end_date = date('Y-m-d', strtotime($request->end_date));
        $reminder->days_to_remind = $request->days;
        $reminder->status = $request->status;
        $reminder->save();

        session()->flash("alert-success", "Remider Updated Successfully!");
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Reminder  $reminder
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Reminder $id)
    {
        Reminder::destroy($request->id);
        session()->flash("alert-success", "Reminder Deleted Successfully!");
        return back();
    }
}
