<?php

namespace App\Http\Controllers;

use SplFileObject;

class ImportProductsController extends Controller
{
    public function __invoke()
    {

        $file = new SplFileObject($path);
        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );
        $file->setCsvControl(';'); // CSV delimiter

        foreach ($file as $row) {
            yield $row; // returns one row at a time
        }
    }
}
