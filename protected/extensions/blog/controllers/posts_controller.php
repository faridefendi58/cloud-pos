<?php

namespace Extensions\Controllers;

use Components\BaseController as BaseController;

class PostsController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['POST'], '/get-slug', [$this, 'get_slug']);
        $app->map(['POST'], '/upload-images', [$this, 'get_upload_images']);
        $app->map(['POST'], '/delete-image/[{id}]', [$this, 'delete_image']);
        $app->map(['GET', 'POST'], '/direct-upload', [$this, 'get_direct_upload']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete'],
                'users'=> ['@'],
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \ExtensionsModel\PostModel();
        $posts = $model->getPosts([ 'just_default' => true]);
        
        return $this->_container->module->render($response, 'posts/view.html', [
            'posts' => $posts
        ]);
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $languages = \ExtensionsModel\PostLanguageModel::model()->findAll();
        $model = new \ExtensionsModel\PostModel('create');
        $categories = \ExtensionsModel\PostCategoryModel::model()->findAll();
        $post_id = 0;

        if (isset($_POST['Post'])){
            $model->status = $_POST['Post']['status'];
            $model->allow_comment = ($_POST['Post']['allow_comment'] == 'on')? 1 : 0;
            $model->post_type = $_POST['Post']['post_type'];
            $model->author_id = $this->_user->id;
            if (!empty($_POST['Post']['tags'])) {
                $model->tags = $_POST['Post']['tags'];
            }
            $model->created_at = date('Y-m-d H:i:s');
            $model->updated_at = date('Y-m-d H:i:s');
            $create = \ExtensionsModel\PostModel::model()->save(@$model);
            if ($create > 0) {
                $post_content = \ExtensionsModel\PostContentModel::model();
                foreach ($_POST['PostContent']['title'] as $lang => $title) {
                    if (!empty($title) && !empty($_POST['PostContent']['content'][$lang])) {
                        $model2 = new \ExtensionsModel\PostContentModel;
                        $model2->post_id = $model->id;
                        $model2->title = $title;
                        if (!empty($_POST['PostContent']['slug'][$lang])){
                            $cek_slug = $post_content->findByAttributes(['slug'=>$_POST['PostContent']['slug'][$lang]]);
                            if ($cek_slug instanceof \RedBeanPHP\OODBBean) {
                                $model2->slug = $_POST['PostContent']['slug'][$lang].'2';
                            } else {
                                $model2->slug = $_POST['PostContent']['slug'][$lang];
                            }
                        } else
                            $model2->slug = $model->createSlug($title);

                        $model2->language = $lang;
                        $model2->content = $_POST['PostContent']['content'][$lang];
                        $model2->meta_keywords = $_POST['PostContent']['meta_keywords'][$lang];
                        $model2->meta_description = $_POST['PostContent']['meta_description'][$lang];
                        $model2->created_at = date("Y-m-d H:i:s");
                        $model2->updated_at = date("Y-m-d H:i:s");
                        $create_content = $post_content->save($model2);
                    }
                }
                $post_in_category = \ExtensionsModel\PostInCategoryModel::model();
                if (!empty($_POST['Post']['post_category']) && is_array($_POST['Post']['post_category'])) {
                    foreach ($_POST['Post']['post_category'] as $ci => $category_id) {
                        $model3 = new \ExtensionsModel\PostInCategoryModel();
                        $model3->post_id = $model->id;
                        $model3->category_id = $category_id;
                        $model3->created_at = date("Y-m-d H:i:s");
                        $post_in_category->save($model3);
                    }
                }
                
                $message = 'Your post is successfully created.';
                $success = true;
                $post_id = $model->id;
            } else {
                $message = 'Failed to create new post.';
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'posts/create.html', [
            'languages' => $languages,
            'status_list' => $model->getListStatus(),
            'categories' => $categories,
            'message' => ($message) ? $message : null,
            'success' => $success,
            'post_id' => $post_id
        ]);
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (empty($args['id']))
            return false;

        $languages = \ExtensionsModel\PostLanguageModel::model()->findAll();
        $model = \ExtensionsModel\PostModel::model()->findByPk($args['id']);
        $post = new \ExtensionsModel\PostModel();
        $categories = \ExtensionsModel\PostCategoryModel::model()->findAll();
        $post_detail = $post->getPostDetail($args['id']);

        if (isset($_POST['Post'])){
            $model->status = $_POST['Post']['status'];
            $model->allow_comment = ($_POST['Post']['allow_comment'] == 'on')? 1 : 0;
            $model->post_type = $_POST['Post']['post_type'];
            if (!empty($_POST['Post']['tags'])) {
                $model->tags = $_POST['Post']['tags'];
            }
            $model->updated_at = date('Y-m-d H:i:s');
            $update = \ExtensionsModel\PostModel::model()->update($model);
            if ($update) {
                $post_content = \ExtensionsModel\PostContentModel::model();
                foreach ($_POST['PostContent']['title'] as $lang => $title) {
                    if (!empty($title) && !empty($_POST['PostContent']['content'][$lang])) {
                        $model2 = \ExtensionsModel\PostContentModel::model()->findByAttributes([ 'post_id'=>$model->id, 'language'=>$lang ]);
                        if (!$model2 instanceof \RedBeanPHP\OODBBean) {
                            $model2 = new \ExtensionsModel\PostContentModel;
                            $model2->created_at = date("Y-m-d H:i:s");
                        }
                        $model2->post_id = $model->id;
                        $model2->title = $title;
                        if (!empty($_POST['PostContent']['slug'][$lang])){
                            if ($_POST['PostContent']['slug'][$lang] != $model2->slug) {
                                $cek_slug = $post_content->findByAttributes(['slug'=>$_POST['PostContent']['slug'][$lang]]);
                                if ($cek_slug instanceof \RedBeanPHP\OODBBean) {
                                    $model2->slug = $_POST['PostContent']['slug'][$lang].'2';
                                } else {
                                    $model2->slug = $_POST['PostContent']['slug'][$lang];
                                }
                            }
                        } else
                            $model2->slug = $post->createSlug($title);

                        $model2->language = $lang;
                        $model2->content = $_POST['PostContent']['content'][$lang];
                        $model2->meta_keywords = $_POST['PostContent']['meta_keywords'][$lang];
                        $model2->meta_description = $_POST['PostContent']['meta_description'][$lang];
                        $model2->updated_at = date("Y-m-d H:i:s");
                        if (!$model2 instanceof \RedBeanPHP\OODBBean)
                            $store = $post_content->save($model2);
                        else
                            $store = $post_content->update($model2);
                        if (!$store){
                            var_dump($post_content->getErrors()); exit;
                        }
                    }
                }
                $post_in_category = \ExtensionsModel\PostInCategoryModel::model();
                if (!empty($_POST['Post']['post_category']) && is_array($_POST['Post']['post_category'])) {
                    foreach ($_POST['Post']['post_category'] as $ci => $category_id) {
                        $model3 = \ExtensionsModel\PostInCategoryModel::model()->findByAttributes([ 'post_id'=>$model->id, 'category_id'=>$category_id ]);
                        if (!$model3 instanceof \RedBeanPHP\OODBBean) {
                            $model3 = new \ExtensionsModel\PostInCategoryModel();
                            $model3->created_at = date("Y-m-d H:i:s");
                            $model3->post_id = $model->id;
                            $model3->category_id = $category_id;
                            $post_in_category->save($model3);
                        }
                    }
                    if (is_array($post_detail['category']) && count($post_detail['category'])>0){
                        foreach ($post_detail['category'] as $ipc => $pc_id) {
                            if (!in_array($pc_id, $_POST['Post']['post_category'])) {
                                $dmodel = \ExtensionsModel\PostInCategoryModel::model()->findByAttributes([ 'post_id'=>$model->id, 'category_id'=>$pc_id ]);
                                $del = \ExtensionsModel\PostInCategoryModel::model()->delete($dmodel);
                            }
                        }
                    }
                }

                $post_detail = $post->getPostDetail($model->id);
                $message = 'Your post is successfully updated.';
                $success = true;
            } else {
                $message = 'Failed to update new post.';
                $success = false;
            }
        }

        $postImages = new \ExtensionsModel\PostImagesModel();
        $images = $post->getImages(['id'=>$model->id]);

        return $this->_container->module->render($response, 'posts/update.html', [
            'languages' => $languages,
            'status_list' => $post->getListStatus(),
            'categories' => $categories,
            'post' => $post_detail,
            'message' => ($message) ? $message : null,
            'success' => $success,
            'postImages' => $postImages,
            'images' => $images
        ]);
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\PostModel::model()->findByPk($args['id']);
        $delete = \ExtensionsModel\PostModel::model()->delete($model);
        if ($delete) {
            $delete2 = \ExtensionsModel\PostContentModel::model()->deleteAllByAttributes(['post_id'=>$args['id']]);
            $delete3 = \ExtensionsModel\PostInCategoryModel::model()->deleteAllByAttributes(['post_id'=>$args['id']]);
            $message = 'Your page is successfully created.';
            echo true;
        }
    }

    public function get_slug($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (!isset($_POST['title'])) {
            return false;
        }

        $model = new \ExtensionsModel\PostModel();
        return $model->createSlug($_POST['title']);
    }

    public function get_upload_images($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (isset($_POST['PostImages'])) {
            $path_info = pathinfo($_FILES['PostImages']['name']['file_name']);
            if (!in_array($path_info['extension'], ['jpg','JPG','jpeg','JPEG','png','PNG'])) {
                echo json_encode(['status'=>'failed','message'=>'Allowed file type are jpg, png']); exit;
                exit;
            }
            $model = new \ExtensionsModel\PostImagesModel();
            $model->post_id = $_POST['PostImages']['post_id'];
            $model->type = $_POST['PostImages']['type'];
            $model->upload_folder = 'uploads/posts';
            $model->file_name = time().'.'.$path_info['extension'];
            $model->alt = $_POST['PostImages']['alt'];
            $model->description = $_POST['PostImages']['description'];
            $model->created_at = date("Y-m-d H:i:s");
            $create = \ExtensionsModel\PostImagesModel::model()->save(@$model);
            if ($create > 0) {
                $uploadfile = $model->upload_folder . '/' . $model->file_name;
                move_uploaded_file($_FILES['PostImages']['tmp_name']['file_name'], $uploadfile);
                echo json_encode(['status'=>'success','message'=>'Successfully uploaded new images']); exit;
            }
        }

        echo json_encode(['status'=>'failed','message'=>'Unable to upload the files.']); exit;
        exit;
    }

    public function delete_image($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($_POST['id'])) {
            return false;
        }

        $model = \ExtensionsModel\PostImagesModel::model()->findByPk($_POST['id']);
        $path = $this->_settings['basePath'].'/../'.$model->upload_folder.'/'.$model->file_name;
        $delete = \ExtensionsModel\PostImagesModel::model()->delete($model);
        if ($delete) {
            if (file_exists($path))
                unlink($path);
            echo true;
        }
        exit;
    }

    /**
     * Direct upload image on the content of post
     * @param $request
     * @param $response
     * @param $args
     * @return mixed
     */
    public function get_direct_upload($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (isset($_FILES['file']['name'])) {
            $path_info = pathinfo($_FILES['file']['name']);
            if (!in_array($path_info['extension'], ['jpg','JPG','jpeg','JPEG','png','PNG'])) {
                return $response->withJson('Tipe dokumen yang diperbolehkan hanya jpg, jpeg, dan png');
            }

            $uploadfile = 'uploads/posts/' . time().'.'.$path_info['extension'];
            move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile);

            return $response->withJson(['location' => $this->getBaseUrl($request).'/'.$uploadfile]);
        }

        return $response->withJson('Terjadi kegagalan saat mengunggah dokumen.');
    }
}