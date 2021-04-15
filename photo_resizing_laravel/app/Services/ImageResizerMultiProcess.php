<?php

namespace App\Services;

class ImageResizerMultiProcess
{
    private $sizes = [];

    public function setSizes($sizes)
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function getSizes()
    {
        return $this->sizes;
    }

    public function executeMultiProcess()
    {
        $childPids = array();
        $sizes = $this->getSizes();

        foreach ($sizes as $size) {
            $pid = pcntl_fork();

            if ($pid == –1) {   //fork failed. May be extreme OOM condition
                die('pcntl_fork failed');
            } elseif ($pid) {   //parent process
                $childPids[] = $pid;
            } else {            //child process
                $status = Lib::resizeImage($size[0], $size[1]);
                exit();
            }
        }

        while (!empty($childPids)) {    //wait for all children to complete
            foreach ($childPids as $key => $pid) {
                $status = null;
                $res = pcntl_waitpid($pid, $status, WNOHANG);

                if ($res == -1 || $res > 0) {   //if the process has already exited
                    unset($childPids[$key]);
                }
            }
            //here sleep() should be used, if the script is in production and doing some heavy process
        }
    }
}
