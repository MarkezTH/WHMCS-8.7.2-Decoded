<?php

namespace WHMCS\Authentication;

class DeviceConfigurationController
{
    private function createErrorResponse($message = NULL, $code = 500)
    {
        if (is_null($message)) {
            $message = "This request could not be processed.";
        }
        return new \WHMCS\Http\Message\JsonResponse(["data" => $message], $code);
    }

    public function generate(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $device = Device::newAdminDevice(\WHMCS\User\Admin::find($request->request()->get("admin_id")), $request->request()->get("description"));
            $roles = $request->request()->get("roleIds");
            if (!empty($roles)) {
                $foundRoles = \WHMCS\Api\Authorization\ApiRole::whereIn("id", $roles)->get();
                foreach ($foundRoles as $role) {
                    $device->addRole($role);
                }
                $secret = $device->secret;
                $device->save();
                $msg = sprintf("Created API Credential identifier \"%s\" for Admin \"%d: %s\"", $device->identifier, $device->admin->id, $device->admin->username);
                logActivity($msg);
                $data = ["body" => view("authentication.partials.generated-api-credentials", ["identifier" => $device->identifier, "secret" => $secret])];
                return new \WHMCS\Http\Message\JsonResponse($data);
            } else {
                return $this->createErrorResponse(["status" => "error", "errorMsg" => "At least one role must be assigned."], 400);
            }
        } catch (\Exception $e) {
            return $this->createErrorResponse();
        }
    }

    public function delete(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $device = Device::find($request->get("id"));
            if ($device) {
                $identifier = $device->identifier;
                if ($device->delete()) {
                    $msg = sprintf("Deleted API Credential identifier \"%s\" for Admin \"%d: %s\"", $identifier, $device->admin->id, $device->admin->username);
                    logActivity($msg);
                }
            }
            $data = ["status" => "okay"];
            return new \WHMCS\Http\Message\JsonResponse($data);
        } catch (\Exception $e) {
            return $this->createErrorResponse();
        }
    }

    public function manage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $deviceId = $request->get("id", 0);
        $device = Device::find($deviceId);
        if (!$device) {
            return $this->createErrorResponse();
        }
        $csrfToken = generate_token("plain");
        $htmlPartial = view("authentication.partials.edit-api-credentials", ["device" => $device, "roles" => \WHMCS\Api\Authorization\ApiRole::all(), "csrfToken" => $csrfToken]);
        return new \WHMCS\Http\Message\JsonResponse(["body" => $htmlPartial]);
    }

    public function update(\WHMCS\Http\Message\ServerRequest $request)
    {
        $deviceId = $request->get("id", 0);
        $device = Device::find($deviceId);
        if (!$device) {
            return $this->createErrorResponse();
        }
        $device->description = $request->get("description");
        $currentRoles = $device->rolesCollection();
        $roleIds = $request->get("roleIds", []);
        if ($roleIds) {
            $roles = \WHMCS\Api\Authorization\ApiRole::whereIn("id", $roleIds)->get();
        } else {
            $roles = [];
        }
        if (count($roles) === 0) {
            return $this->createErrorResponse(["status" => "error", "errorMsg" => "At least one role must be assigned."], 400);
        }
        foreach ($currentRoles as $roleId => $role) {
            if (!$roles->find($roleIds)) {
                $device->removeRole($role);
            }
        }
        foreach ($roles as $role) {
            $device->addRole($role);
        }
        $device->save();
        return new \WHMCS\Http\Message\JsonResponse(["status" => "success", "dismiss" => true]);
    }

    public function updateFields(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $result = [];
            $id = $request->get("pk");
            $name = $request->get("name");
            $value = $request->get("value", "");
            if ($id && $name && $name == "description") {
                $device = Device::find($id);
                if ($device) {
                    $device->description = $value;
                    $device->save();
                }
                $result = ["status" => "okay"];
            }
            return new \WHMCS\Http\Message\JsonResponse($result);
        } catch (\Exception $e) {
            return $this->createErrorResponse();
        }
    }

    public function getDevices(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $adminDevices = Device::where("is_admin", "=", 1)->get();
            $tableData = (new \WHMCS\Admin\Setup\Authorization\Api\View\DeviceHelper())->getTableData($adminDevices);
            return new \WHMCS\Http\Message\JsonResponse(["data" => $tableData]);
        } catch (\Exception $e) {
            return $this->createErrorResponse();
        }
    }

    public function createNew(\WHMCS\Http\Message\ServerRequest $request)
    {
        $adminUserSelectOptions = [];
        $adminUsers = \WHMCS\User\Admin::orderBy("firstname")->orderBy("lastname")->get();
        foreach ($adminUsers as $admin) {
            $adminUserSelectOptions[] = "<option value=\"" . $admin->id . "\">" . $admin->firstname . " " . $admin->lastname . "</option>";
        }
        $adminUserSelectOptions = implode("\n", $adminUserSelectOptions);
        $csrfToken = generate_token("plain");
        $body = view("authentication.partials.create-api-credentials", ["adminUserSelectOptions" => $adminUserSelectOptions, "roles" => \WHMCS\Api\Authorization\ApiRole::all(), "csrfToken" => $csrfToken]);
        return new \WHMCS\Http\Message\JsonResponse(["title" => \AdminLang::trans("apicredentials.create"), "body" => $body]);
    }

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $aInt = new \WHMCS\Admin("Manage API Credentials", false);
        $aInt->title = \AdminLang::trans("setup.apicredentials");
        $aInt->sidebar = "config";
        $aInt->icon = "admins";
        $aInt->helplink = "API_Authentication_Credentials";
        $aInt->setResponseType($aInt::RESPONSE_HTML_MESSAGE);
        $csrfToken = generate_token("plain");
        $modalRole = $aInt->modal("NewAPIRole", \AdminLang::trans("apirole.create"), view("authorization.partials.api-role-detail", ["apiCatalog" => \WHMCS\Api\V1\Catalog::get(), "csrfToken" => $csrfToken]), [["title" => "Cancel"], ["type" => "submit", "title" => \AdminLang::trans("general.save"), "class" => "btn-primary", "onclick" => "false"]], "large", "primary");
        $aInt->content = view("authentication.manage-api-credentials", ["modalRole" => $modalRole, "csrfToken" => $csrfToken]);
        return $aInt->display();
    }
}
