<?php

namespace WHMCS\Scheduling\Jobs;

class AsyncJobController
{
    public function runJob(\WHMCS\Http\Message\ServerRequest $request)
    {
        ignore_user_abort(true);
        $jobQueueId = $request->get("jobId");
        $job = $jobQueueId ? Queue::find($jobQueueId) : NULL;
        if (!$job || !$job->async) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => "Cannot find a matching async job"], 404);
        }
        $authHash = $request->get("authHash");
        if (!$job->validateAuthHash($authHash)) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => "Invalid auth hash"], 405);
        }
        try {
            $job->executeJob();
            $job->delete();
        } catch (\Throwable $e) {
            logActivity("Async job experienced a failure: " . $e->getMessage());
        }
        return new \WHMCS\Http\Message\JsonResponse([]);
    }
}
