<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ImageUploadController extends Controller
{

    private $sizes = [[50, 50], [100, 100], [150, 150], [200, 200], [250, 250], [300, 300], [350, 350], [400, 400], [450, 450], [500, 500], [1000, 1000]];

    public static function resizeImage($height, $width,$file)
    {
        if(!Storage::disk('public')->files('resized'))
        {
            Storage::disk('public')->makeDirectory('resized');
        }

        $image =  public_path('storage/').$file;
        $newImage = public_path('storage/resized/').time().rand(0,100).$width.'X'.$height.'.jpeg';

        list($originalwidth, $originalheight) = getimagesize($image);
        $tmpImage = imagecreatetruecolor($width, $height);
        $copiedImage = imagecreatefromjpeg($image);
        imagecopyresampled($tmpImage, $copiedImage, 0, 0, 0, 0, $width, $height, $originalwidth, $originalheight);

        return imagejpeg($tmpImage, $newImage);
    }

    public function imageUpload()
    {
        $files = Storage::disk('public')->files('images');
        return view('imageUpload',compact('files'));
    }
    public function showResizedImages()
    {
        $files = Storage::disk('public')->files('resized');
        return view('show-images',compact('files'));
    }
    public function downloadImages()
    {

        $files = Storage::disk('public')->files('resized');
        $zipname = 'file.zip';
        $zip = new ZipArchive;
        $zip->open($zipname, ZipArchive::CREATE);
        foreach ($files as $file) {
        $zip->addFile($file);
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.$zipname);
        header('Content-Length: ' . filesize($zipname));
        readfile($zipname);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function imageUploadPost(Request $request)
    {
        Storage::disk('public')->deleteDirectory('images');
        Storage::disk('public')->deleteDirectory('resized');


        foreach($request->file('images') as $image)
        {
            $imageName = Storage::disk('public')->putFile('images',$image);


           // multi-process
          // $this->executeMultiProcess($imageName);

           // single-process
           $sizes = $this->getSizes();
           foreach ($sizes as $size) {
           $this->resizeImage($size[0],$size[1],$imageName);
           }

        }


        /* Store Image in Public Folder */
     //   $request->image->move(public_path('images'), $imageName);

        /* Store $imageName name in DATABASE from HERE */

        return redirect('image-show');
    }


    public function setSizes($sizes)
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function getSizes()
    {
        return $this->sizes;
    }

    public function executeMultiProcess($file)
    {
        $childPids = array();
        $sizes = $this->getSizes();

        foreach ($sizes as $size) {
            $pid = pcntl_fork();

            if ($pid == -1) {   //fork failed. May be extreme OOM condition
                die('pcntl_fork failed');
            } elseif ($pid) {   //parent process
                $childPids[] = $pid;
            } else {            //child process
                $status = $this->resizeImage($size[0], $size[1],$file);
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
