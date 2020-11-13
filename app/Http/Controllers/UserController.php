<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

//don't forget to implement database transaction
class UserController extends Controller
{
    //show edit profile
    public function index()
    {
        $user = auth()->user();
        return route('user-profile', ['user' => $user]);
    }
    //update profile of user
    public function updateUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
    }
    //change password when user is logged in
    public function changePassword(Request $request)
    {
        //compare old password
        $request->validate([
            'old_password' => 'min:6|string|required',
            'password' => 'min:6|string|required|confirmed',
        ]);
        DB::beginTransaction();
        try {
            $user = User::where('id', auth()->user()->id)->first();
            if (!Hash::check($request->password, $user->password)) { //comparing old password with the database
                User::find(auth()->user()->id)->update([
                    'password' => Hash::make($request->password),
                ]);
            } else {
                return redirect()->intended()->with([
                    'message' => 'Old password not matched',
                ]);
            }
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->intended()->with([
                'message' => $e,
            ]);
        }
        DB::commit();
        Auth::logout(); //after changing password, logout the user and send them to get login from new password
        return redirect()->route('login')->with([
            'message' => 'Password updated successfully',
        ]);
    }
    //change email
    public function changeEmail(Request $request)
    {
        //compare existing email
        $request->validate([
            'email' => 'required|email|max:255',
            'new_email' => 'required|email|max:255',
        ]);
        if (auth()->user()->email == $request->email) {
            DB::beginTransaction();
            try {
                if (User::where('email', $request->email)->exists()) { //checking if new email is already registered
                    return redirect()->intended()->with([
                        'message' => 'Email already registered',
                    ]);
                } else {
                    User::find(auth()->user()->id)->update([ //updating email in database
                        'email' => $request->email,
                    ]);
                }
            } catch (Exception $e) {
                DB::rollback();
                return redirect()->intended()->with([
                    'message' => $e, //'Something went wrong'
                ]);
            }
            DB::commit();
            return redirect()->intended()->with([ //redirect to same page
                'message' => 'Email updated successfully',
            ]);
        } else {
            return ['message' => 'email not matched'];
        }
    }
    //soft deleting user from the database
    public function destroyUser()
    {
        //soft delete the user
        DB::beginTransaction();
        try {
            User::destroy(auth()->user()->id);
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->intended(route('home'))->with([
                'message' => 'Something went wrong!',
            ]);
        }
        DB::commit();
        return redirect()->route('home')->with([
            'message' => 'Account and all related data deleted successfully',
        ]);
    }
}
