<?php

declare(strict_types=1);

namespace App\Exceptions;

use Amp\Parallel\Worker\TaskFailureException;
use Exception;
use Google\Cloud\ErrorReporting\Bootstrap;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     */
    public function report(Throwable $exception): void
    {
        // Report to stackdriver on app engine
        if (isset($_SERVER['GAE_SERVICE']) && $this->shouldReport($exception)) {
            Bootstrap::exceptionHandler($exception);
        }

        if ($exception instanceof TaskFailureException) {

            $taskException = new TaskException(
                $exception->getOriginalMessage(),
                $exception->getOriginalTraceAsString(),
                $exception->getOriginalCode()
            );

            parent::report($taskException);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception): Response
    {
        return parent::render($request, $exception);
    }
}
