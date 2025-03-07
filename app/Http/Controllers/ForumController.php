<?php

namespace App\Http\Controllers;

use App\Mail\ForumVerificationMail;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Batch;
use App\Models\Student;
// use Illuminate\Http\Request;
// use Illuminate\Support\Arr;

use Image;
use File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForumController extends Controller
{
    protected $messages = [
        'required' => 'The :attribute field is required.',
        'same' => 'The :attribute and :other must match.',
        'size' => 'The :attribute must be exactly :size.',
        'min' => 'The :attribute must be greater than :min characters.',
        'max' => 'The :attribute must be less than :max characters.',
        'between' => 'The :attribute value :input is not between :min - :max.',
        'in' => 'The :attribute must be one of the following types: :values',
        'unique' => 'The :attribute is already in use.',
        'exists' => 'The :attribute is invalid.',
        'regex' => 'The :attribute format is invalid.',
        'email' => 'Invalid email.',
        'string' => 'The :attribute should be a string.',
    ];

    //forum selection method
    public function index()
    {
        return view('forum.view');
    }

    //student forum selection method
    public function studentForum()
    {
        $departments = [];
        $faculties = Faculty::select('id', 'name')->orderBy('name')->get()->toArray();
        $facultyCodes = Faculty::select('code')->orderBy('name')->get()->toArray();
        $departmentCodesAHS = Department::select('code')->where('faculty_id', Faculty::where('code', "AHS")->first()->id)->get()->toArray();
        $batches = Batch::select('id')->get()->toArray();

        // Get all the departments of each faculty
        foreach ($faculties as $key => $faculty) {
            $departments[$faculty['id']] = Faculty::find($faculty['id'])->departments()->select('id', 'name')->get()->toArray();
        }

        // dd($departments);
        return view('forum.student')->with('faculties', $faculties)->with('departments', json_encode($departments))->with('batches', $batches)->with('fcodes', json_encode($facultyCodes))->with('dcodes', json_encode($departmentCodesAHS));
    }

    //Email verification and password setting function
    public function verification($username)
    {        
        return view('forum.verification', compact('username'));
    }

    //updating the password field 
    public function updatePassword($username)
    {
        $data = request()->validate([
            'password' => ['required', 'string', 'min:'.env("USERS_PASSWORD_MIN"), 'max:'.env("USERS_PASSWORD_MAX"), 'confirmed'],
        ], $this->messages);

        $data['password'] = Hash::make($data['password']);
        User::where('username', $username)->update($data);

        return redirect('/');
    }

    // Academic staff froum selection method. (TO BE DEVELOPED)
    public function staffForum()
    {
        return view('comingsoon.comingsoon');
        // return view('forum.staff');
    }

    // Recieve students' forum data
    public function storeStudent()
    {   
        $user = request()->validate([
            'username' => ['required','string', 'min:'.env("USERS_USERNAME_MIN"), 'max:'.env("USERS_USERNAME_MAX"), 'unique:users'],
            'email' => ['required', 'email:rfc,dns', 'unique:users'],
        ], $this->messages);

        $user['usertype'] = env('STUDENT');

        // Process the registration number
        request()['regNo'] = $this->createRegNo(request()->faculty_id, request()->batch_id, request()->department_id, request()->regNo);

        // dd(request()->regNo);

        $student = request()->validate([
            'preferedname' => ['required','string', 'max:'.env("STUDENTS_PREFEREDNAME_MAX")],
            'fullname' => ['required','string', 'max:'.env("STUDENTS_FULLNAME_MAX")],
            'initial' => ['required','string', 'max:'.env("STUDENTS_INITIAL_MAX")],
            'address' => ['required','string', 'max:'.env("STUDENTS_ADDRESS_MAX")],
            'city' => ['required','string', 'max:'.env("STUDENTS_CITY_MAX")],
            'province' => ['required','string', 'max:'.env("STUDENTS_PROVINCE_MAX")],
            'faculty_id' => ['required','int','exists:faculties,id'],
            'department_id' => ['required','int', 'exists:departments,id'],
            'batch_id' => ['required','int','exists:batches,id'],
            'regNo' => ['required','string','unique:students', 'regex:/^([A-Z]{1,3}\/{1}+\d{2}?(\/{1}+[A-Z]{3})?\/{1}+\d{3})$/'],
            'image' => ['required','image'],
        ], $this->messages);

        
        // Create the student's registration number        
        $facultyCode = Faculty::where('id', $student['faculty_id'])->firstOrFail()->code;

        // Create the user
        User::create($user);

        // Retrive the foreign key of students table
        $student['id'] = User::where('username', $user['username'])->firstOrFail()->id;

        // Create the image directory if not exists
        $paths = $this->createDirectory($facultyCode, 'Student', $student['batch_id'], $student['regNo']);

        // Store the image in the respective directory
        $path = $this->storeImage($paths, $student['regNo'], $student['image']);

        // Change the image path in the user data
        $student['image'] = $path;

        // Create student
        Student::create($student);
        
        // Delete user from the users table if the user is not in the students table
        if(!Student::find($student['id'])->first()) {
            User::find($student['id'])->delete();
        }

        //Mail sending procedure
        Mail::to($user['email'])->send(new ForumVerificationMail($user["username"]));

        return redirect('/forum/student')->with('message', 'Forum data entered Succesfully!!');
    }

    /**
     * Process the registration number
     */
    private function createRegNo($faculty_id, $batch_id, $department_id, $number)
    {
        $facultyCode = Faculty::where('id', $faculty_id)->firstOrFail()->code;
        $RegNo = $facultyCode.'/'.$batch_id.'/';

        if($facultyCode == "AHS") {
            $departmentCode = Department::select('code')->where('id', $department_id)->first()->code;
            $RegNo = $RegNo.$departmentCode.'/';
        }

        $RegNo = $RegNo.$number;

        return $RegNo;
    }

    /**
     * Create the directory if not exists
     * @return paths
     */
    private function createDirectory($faculty_code, $type, $batch_id, $regNo) 
    {
        /**
         * chmode codes has 3 digits (Owner, Group, World)
         * Permission (4 = read only, 7 = read and write and execute)
         */
        $chmode = 744;
        $tmpPath = "";

        if($type == "Student") {
            $tmpPath = $faculty_code.'/'.$type.'/'.$batch_id.'/';

            // For AHS faculty
            if($faculty_code == "AHS") {
                $deptCode = explode('/', $regNo)[2];
                $tmpPath = $tmpPath.$deptCode.'/';
            }

        } else {
            $tmpPath = $faculty_code.'/'.$type.'/';
        }

        // Define and initialize paths for different directories
        $paths = [
            'image_path' => public_path('uploads/images/'.$tmpPath),
            'thumbnail_path' => public_path('uploads/thumbs/'.$tmpPath)
        ];

        // Create paths
        foreach ($paths as $key => $path) {
            if(!File::isDirectory($path)){
                File::makeDirectory($path, $chmode, true, true);
            }
        }

        return $tmpPath;
    }

    /**
     * Change image name
     * Save image in respective directory
     */
    private function storeImage($path, $regNo, $file) 
    {     
        // Create the image name
        $number = explode('/', $regNo);
        $number = $number[count($number)-1];
        
        $imageName = $number.'.png';

        // Load the image, resize it and then save the profile image
        $image = Image::make($file)->fit(400, 400);
        $image->save(public_path('uploads/images/'.$path).$imageName);

        // Resize the image and save the tumbnail
        $image->resize(150,150);
        $image->save(public_path('uploads/thumbs/'.$path).$imageName);

        return $path.$imageName;
    }

    // Resubmission forum data
    public function resubmission($username)
    {
        if (! request()->hasValidSignature()) {
            abort(401);
        }

        $departments = [];
        $faculties = Faculty::select('id', 'name')->orderBy('name')->get()->toArray();
        $facultyCodes = Faculty::select('code')->orderBy('name')->get()->toArray();
        $departmentCodesAHS = Department::select('code')->where('faculty_id', Faculty::where('code', "AHS")->first()->id)->get()->toArray();
        $batches = Batch::select('id')->get()->toArray();

        // Get all the departments of each faculty
        foreach ($faculties as $key => $faculty) {
            $departments[$faculty['id']] = Faculty::find($faculty['id'])->departments()->select('id', 'name')->get()->toArray();
        }

        // Retrive user information to auto fill data fields
        $user = User::where('username', $username)->firstOrfail();
        $student = $user->students()->firstOrfail();
        $student->email = $user->email;
        $student->username = $user->username;

        return view('forum.resubmit')->with('faculties', $faculties)->with('departments', json_encode($departments))->with('batches', $batches)->with('fcodes', json_encode($facultyCodes))->with('dcodes', json_encode($departmentCodesAHS));
    }
}
