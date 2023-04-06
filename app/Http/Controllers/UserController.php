<?php

namespace App\Http\Controllers;


use App\Libraries\Responder\Facades\ResponderFacade;
use App\Libraries\Responder\ResponseBuilder;
use DateTime;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class UserController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $Data =  $this->validate($request , [
            'username' => 'required',
            'password' => 'required',
            'date' => 'required',
            'limit' => 'required'
        ]);

        $username = $Data['username'];
        $password = $Data['password'];
        $date = $Data['date'];
        $limit = $Data['limit'];

        // Execute the adduser command
        shell_exec("sudo useradd {$username} -p $(openssl passwd -1 {$password})");
        shell_exec("chage -E {$date} {$username}");
        shell_exec("sudo bash -c echo '{$username} hard maxlogins {$limit}' >> /etc/security/limits.conf");

        $response = new ResponseBuilder();
        return  $response->setMessage('User created successfully')->respond();
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $this->validate($request , [
           'username' => 'required'
        ]);

        $username = $data['username'];

        shell_exec("usermod -L -e 1 {$username}");

        $response = new ResponseBuilder();
        return  $response->setMessage('User disable successfully')->respond();
    }

    public function enable(Request $request): JsonResponse
    {
        $data = $this->validate($request , [
            'username' => 'required'
        ]);

        $username = $data['username'];

        shell_exec("usermod -e -1 -U {$username}");

        $response = new ResponseBuilder();
        return  $response->setMessage('User enable successfully')->respond();
    }

    public function delete(Request $request): JsonResponse
    {
        $data = $this->validate($request , [
            'username' => 'required'
        ]);

        shell_exec("userdel -r {$data['username']}");

        $response = new ResponseBuilder();

        return  $response->setMessage('User delete successfully')->respond();
    }

    public function check_user_exsit(Request $request): JsonResponse
    {
        $data = $this->validate($request , [
            'username' => 'required'
        ]);

        $output = '';

        exec("id -u {$data['username']}", $output);

        if (count($output) > 0 ) {
            $response = new ResponseBuilder();
            return  $response->setData(true)->setMessage('User exists')->respond();
        }else{
            $response = new ResponseBuilder();
            return  $response->setData(false)->setStatusCode(404)->setMessage('User does not exist!')->respond();
        }
    }

    public function is_user_active(Request $request)
    {
        $data = $this->validate($request,[
            'username' => 'required'
        ]);

        $response = new ResponseBuilder();

        $result = shell_exec("chage -l {$data['username']}");
        $output = explode(PHP_EOL, trim($result));
        foreach ($output as $line) {
            if (strpos($line, 'Account expires') !== false) {
                list($label, $dateStr) = explode(':', $line, 2);
                $dateStr = trim($dateStr);
                if ($dateStr === 'never') {
                    return  $response->setData(true)->setMessage('Unlimited users')->respond();
                }
                $expirationDate = DateTime::createFromFormat('M d, Y', $dateStr);
                $expirationDate = $expirationDate->format('Y-m-d H:i:s');
                if (new DateTime() > $expirationDate) {
                    return  $response->setData(true)->setMessage("The user is active , Expiration date : {$expirationDate}")->respond();
                } else {
                    return  $response->setData(true)->setMessage("The user is inactive , Expiration date : {$expirationDate}")->respond();
                }
            }
        }
        return  $response->setData(false)->setMessage("There is a problem or user does not exist!")->respond();
    }
}
