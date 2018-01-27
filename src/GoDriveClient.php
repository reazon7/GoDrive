<?php
namespace REAZON\GoDrive;

use Illuminate\Support\Facades\Session;
use REAZON\GoDrive\Exceptions\ServiceException;
use Google_Client;

class GoDriveClient {

    protected $config;
    protected $client;
    protected $token;
    protected $fileToken;
    protected $isUnlimited;
    protected $appName;
    protected $dirInfo;
    protected $tokenKey = "REAZON.GoDrive.Token";
    protected $outOfCapacity = "REAZON.GoDrive.OutOfCapacity";
    private $clientDrive;
    private $clientOAuth2;
    
    function __construct(array $config = null, $userEmail = '') {

        $this->config = isset($config) ? $config : config('godrive');
        $this->client = new Google_Client(array_get($this->config, 'google', ''));

        try {
            $this->client->setScopes(array_get($this->config, 'google.scopes', []));

            $this->fileToken = array_get($this->config, 'user.fileToken', '');
            $this->isUnlimited = array_get($this->config, 'user.isUnlimited', false);
            $this->appName = array_get($this->config, 'google.application_name', 'REAZON APP');
            $this->dirInfo = array_get($this->config, 'user.isUnlimited', false);
            if (array_get($this->config, 'service.enable', false)) {
                $this->auth($userEmail);
            }

            if(Session::has($this->tokenKey)) {
                $this->token = Session::get($this->tokenKey);
            } else if($this->fileToken) {
                if(file_exists($this->fileToken))
                    $this->token = json_decode(file_get_contents($this->fileToken), true);
            }

            if(!empty($this->token)) {
                $this->client->setAccessToken($this->token);
            } else if(request()->has("code")) {
                $this->client->authenticate(request()->input("code"));
                $this->token = $this->client->getAccessToken();
                $this->client->setAccessToken($this->token);
                if($this->fileToken) {
                    if(!file_exists(dirname($this->fileToken))) {
                        mkdir(dirname($this->fileToken), 777, true);
                    }
                    file_put_contents($this->fileToken, json_encode($this->token));
                }
            } else if(!empty(array_get($this->config, 'google.redirect_uri', ''))) {
                $this->authPopup();
            }

            if($this->client->isAccessTokenExpired() and $this->token) {
                $tokens = (is_array($this->token)) ? $this->token : json_decode($this->token);
                $refreshToken = $tokens["refresh_token"];
                $this->client->refreshToken($refreshToken);
                $this->token = $this->client->getAccessToken();
            }

            if(!$this->client->isAccessTokenExpired()) {
                if(!empty($this->fileToken)) {
                    if(!file_exists(dirname($this->fileToken))) {
                        mkdir(dirname($this->fileToken), 777, true);
                    }
                    file_put_contents($this->fileToken, json_encode($this->token));
                } else {
                    Session::put($this->tokenKey, $this->token);
                }
            }

        } catch(ServiceException $ex) {
            if(!empty(array_get($this->config, 'google.redirect_uri', ''))) {
                $this->authPopup();
            }
        }

        try {
            if(!Session::has($this->outOfCapacity)){
                $this->quota = $this->getClientDrive()->about->get(array("fields" => "storageQuota"))->getStorageQuota();
                Session::put($this->outOfCapacity, round($this->quota->getLimit()) <= round($this->quota->getUsage()));
            }

            if(!$this->isUnlimited && Session::get($this->outOfCapacity)) {
                echo 'Drive Capacity is FULL';
                $this->authPopup();
            }
        } catch (\Exception $ex) {
            
        }
    }

    public function isDirectoryExists($name, $parentId = null) {
        if(!is_null($parentId)){
            $params = [
                'q' => "mimeType = 'application/vnd.google-apps.folder' and name = '" . $name . "' and '$parentId' in parents",
                'pageSize' => 1
            ];
        }else{
            $params = [
                'q'=>"mimeType = 'application/vnd.google-apps.folder' and name = '" . $name . "'",
                'pageSize'=>1
            ];
        }

        $gquery = $this->getClientDrive()->files->listFiles($params);
        $sysdir = $gquery->getFiles();

        if(empty($sysdir)) {
            $sysdir = $this->newDirectory($name, $parentId, "public");
        } else {
            $sysdir = $sysdir[0];
        }

        return $sysdir;
    }

    public function uploadFile($path, $title, $parentId=null, $allow= "public") {
        $newFile = new \Google_Service_Drive_DriveFile();
        if ($parentId != null) {
            $newFile->setParents(array($parentId));
        }

        $newFile->setName($title);
        $newFile->setDescription($this->appName . " file uploaded " . gmdate("jS F, Y H:i A") . " GMT");
        $newFile->setMimeType(mime_content_type($path));

        $permission = $this->getFilePermissions($allow);
        $remoteNewFile = $this->getClientDrive()->files->create($newFile, array(
            'data'=>file_get_contents($path),
            'mimeType'=>  mime_content_type($path)
        ));
        $fileId = $remoteNewFile->getId();

        if(!empty($fileId)) {
            $this->getClientDrive()->permissions->create($fileId, $permission);
            return $remoteNewFile;
        }
    }

    public function newDirectory($folderName, $parentId=null, $allow="private") {
        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($folderName);
        $file->setMimeType('application/vnd.google-apps.folder');

        if ($parentId != null) {
            $file->setParents(array($parentId));
        }

        $createdFile = $this->getClientDrive()->files->create($file, array(
            'mimeType'=>'application/vnd.google-apps.folder'
        ));

        $permission = new \Google_Service_Drive_Permission();
        switch($allow):
            case "private":
                $permission->setType('default');
                $permission->setRole('owner');
                break;

            default:
                $permission->setAllowFileDiscovery(true);
                $permission->setType('anyone');
                $permission->setRole('reader');
                break;
        endswitch;

        $this->getClientDrive()->permissions->create($createdFile->getId(), $permission);

        return $createdFile;
    }

    public function removeFile($id) {
        return $this->getClientDrive()->files->delete($id);
    }

    public function getFilePermissions($allow="private") {
        $permission = new \Google_Service_Drive_Permission();
        switch($allow) {
            case "private":
                $permission->setType('user');
                $permission->setRole('owner');
                break;

            case "public":
                $permission->setAllowFileDiscovery(true);
                $permission->setType('anyone');
                $permission->setRole('reader');
                break;

            default:
                $permission->setType('anyone');
                $permission->setRole('reader');
                break;
        }

        return $permission;
    }

    public function getUser() {
        return $this->getClientOauth2()->userinfo->get();
    }

    private function getClientDrive() {
        if(isset($this->clientDrive)) {
            return $this->clientDrive;
        }

        return $this->clientDrive = new \Google_Service_Drive($this->client);
    }

    private function getClientOauth2() {
        if(isset($this->clientOAuth2)) {
            return $this->clientOAuth2;
        }

        return $this->clientDrive =  new \Google_Service_Oauth2($this->client);
    }

    protected function auth($userEmail = '') {
        if ($this->useAssertCredentials($userEmail)) {
            return;
        }

        $this->client->useApplicationDefaultCredentials();
    }

    protected function useAssertCredentials($userEmail = '') {
        $serviceJsonUrl = array_get($this->config, 'service.file', '');

        if (empty($serviceJsonUrl)) {
            return false;
        }

        $this->client->setAuthConfig($serviceJsonUrl);
        
        if ($userEmail) {
            $this->client->setSubject($userEmail);
        }

        return true;
    }

    public function __call($method, $parameters) {
        if (method_exists($this->client, $method)) {
            return call_user_func_array([$this->client, $method], $parameters);
        }

        throw new \BadMethodCallException(sprintf('Method [%s] does not exist.', $method));
    }


    private function authPopup() {
        $authUrl = $this->client->createAuthUrl();
        $script = <<< HTML

<script>
    var w = 500;
    var h = 400;
    var left = (screen.width/2)-(w/2);
    var top = (screen.height/2)-(h/2);
    var win = window.open("$authUrl", '_blank', 'width='+w+', height='+h+', top='+top+', left='+left+', toolbar=0, location=0, menubar=0, scrollbars=0, resizable=0, opyhistory=no');
    win.focus();
</script>
 
HTML;
        
        echo $script;

    }

}