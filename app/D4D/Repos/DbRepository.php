<?php
/**
 * Created by PhpStorm.
 * User: joshhead
 * Date: 2/2/15
 * Time: 10:28 AM
 */

namespace D4D\Repos;


abstract class DbRepository {

    public function getVisitorId($id){

        return $this->model->find($id);
    }


}