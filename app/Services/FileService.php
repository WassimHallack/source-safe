<?php

namespace App\Services;

use App\Http\Requests\File_add_Request;
use App\Http\Requests\File_check_in_Request;
use App\Http\Requests\File_destroy_Request;
use App\Http\Requests\File_edit_Request;
use App\Http\Requests\File_get_Request;
use App\Repositories\AddFileRequestRepository;
use App\Repositories\AddFileRequestToUserRepository;
use App\Repositories\FileOperationRepository;
use App\Repositories\FileRepository;
use App\Repositories\GroupRepository;
use App\Repositories\UserFileRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File as LaravelFile;

class FileService
{
    public function get(File_get_Request $request)
    {
        $group_id = $request['group_id'];
        $files = GroupRepository::find($group_id)->files;

        return response()->json([
            'status' => true,
            'response' => $files,
        ], 200);
    }

    public function add(File_add_Request $request)
    {
        $data = $request->all();
        $logged_in_user = Auth::user();;

        $file = $data['file'];
        $file_name = $file->getClientOriginalName();
        $isFree = $data['isFree'];
        $group_id = $data['group_id'];
        $user_id = $data['user_id'];

        $group = GroupRepository::find($group_id);
        if ($group['user_id'] === $logged_in_user['id']) {
            $file_path = 'Groups/' . $group['name'] . "/" . $file_name;
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $file->storeAs($file_path, "1." . $file_extension);

            $file_data['name'] = $file_name;
            $file_data['group_id'] = $group_id;

            if (!$isFree) {
                $file_data['isFree'] = false;
                $file_record = FileRepository::create($file_data);

                $user_file_data['user_id'] = $user_id;
                $user_file_data['file_id'] = $file_record['id'];
                UserFileRepository::create($user_file_data);

                $user_file_data['operation'] = 'check-in';
                FileOperationRepository::create($user_file_data);
            } else {
                $file_data['isFree'] = true;
                FileRepository::create($file_data);
            }
        } else {
            $file_path = 'Add File Requests/' . $group['name'];
            $file->storeAs($file_path, $file_name);

            $add_file_request_data['group_id'] = $group_id;
            $add_file_request_data['name'] = $file_name;

            if (!$isFree) {
                $add_file_request_data['isFree'] = false;
                $add_file_request_record = AddFileRequestRepository::create($add_file_request_data);

                $add_file_request_to_user_data['add_file_request_id'] = $add_file_request_record['id'];
                $add_file_request_to_user_data['user_id'] = $user_id;
                AddFileRequestToUserRepository::create($add_file_request_to_user_data);
            } else {
                $add_file_request_data['isFree'] = true;
                AddFileRequestRepository::create($add_file_request_data);
            }
        }

        return response()->json([
            'status' => true,
            'response' => 'The file saved successfully.'
        ], 200);
    }

    public function edit(File_edit_Request $request)
    {
        $data = $request->all();
        $user = Auth::user();

        $group_id = $data['group_id'];
        $group = GroupRepository::find($group_id);
        $file = $data['file'];
        $file_name = $file->getClientOriginalName();

        $conditions = [
            'name' => $file_name,
            'group_id' => $group_id,  // 'group_id' => ['>=', $group_id]
            'isFree' => false,
        ];
        $file_record = FileRepository::findByConditions($conditions);

        $conditions = [
            'user_id' => $user['id'],
            'file_id' => $file_record['id']
        ];
        $user_file = UserFileRepository::findByConditions($conditions);
        UserFileRepository::delete($user_file);

        $values = [
            'isFree' => true,
        ];
        FileRepository::update($file_record, $values);

        $data = [
            'user_id' => $user['id'],
            'file_id' => $file_record['id'],
            'operation' => 'check-out'
        ];
        FileOperationRepository::create($data);

        $path = storage_path('app\\Groups\\' . $group['name'] . '\\' . $file_name);
        $fileCount = count(LaravelFile::files($path));

        $path = 'Groups/' . $group['name'] . "/" . $file_name;
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $file->storeAs($path, $fileCount . "." . $file_extension);

        return response()->json([
            'status' => true,
            'response' => 'The file updated successfully.'
        ]);
    }

    public function destroy(File_destroy_Request $request)
    {
        $file_id = $request['file_id'];
        $file = FileRepository::find($file_id);

        FileRepository::delete($file);

        return response()->json([
            'status' => true,
            'response' => 'The file deleted successfully.'
        ], 200);
    }

    public function check_in(File_check_in_Request $request)
    {
        $user = Auth::user();

        foreach ($request['files'] as $file) {
            $data = [
                'user_id' => $user['id'],
                'file_id' => $file['id']
            ];
            UserFileRepository::create($data);

            $data['operation'] = 'check-in';
            FileOperationRepository::create($data);

            $values = ['isFree' => 0];
            FileRepository::update($file, $values);
        }

        return response()->json([
            'status' => true,
            'response' => "Files checked in successfully."
        ]);
    }
}
