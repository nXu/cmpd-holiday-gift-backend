<?php

namespace App\Base\Controllers;

use DB;
use App\Base\Services\ImageService;
use App\Language;
use App\Http\Controllers\Controller;
use FormBuilder;
use Illuminate\Http\Request;
use Laracasts\Flash\Flash;

abstract class AdminController extends Controller
{
    /**
     * Model name
     *
     * @var string
     */
    protected $model = "";

    /**
     * Form class path
     *
     * @var string
     */
    protected $formPath = "";

    /**
     * Current language
     *
     * @var mixed
     */
    protected $language;

    /**
     * AdminController constructor.
     */
    public function __construct()
    {
        $this->model = $this->getModel();
        $this->formPath = $this->getFormPath();
        $this->language = session('current_lang');
    }

    /**
     * Show the form for creating a new category.
     *
     * @return Response
     */
    public function create()
    {
        return $this->getForm();
    }

    /**
     * Get form
     *
     * @param null $object
     * @return \BladeView|bool|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getForm($object = null)
    {
        if ($object) {
            $url =  $this->urlRoutePath("update", $object);
            $method = 'PATCH';
            $path = $this->viewPath("edit");
        } else {
            $url =  $this->urlRoutePath("store", $object);
            $method = 'POST';
            $path = $this->viewPath("create");
        }
        $form = $this->createForm($url, $method, $object);
        return view($path, compact('form', 'object'));
    }

    /**
     * Create form
     *
     * @param $url
     * @param $method
     * @param $model
     * @return \Kris\LaravelFormBuilder\Form
     */
    protected function createForm($url, $method, $model)
    {
        return FormBuilder::create($this->formPath, [
                'method' => $method,
                'url' => $url,
                'model' => $model
            ], $this->getSelectList());
    }

    /**
     * Create, flash success or error then redirect
     *
     * @param $class
     * @param $request
     * @param bool|false $imageColumn
     * @param string $path
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function createFlashRedirect($class, $request, $imageColumn = false, $path = "index")
    {
        $model = $class::create($this->getData($request, $imageColumn));
        $model->id ? Flash::success(trans('admin.create.success')) : Flash::error(trans('admin.create.fail'));
        return $this->redirectRoutePath($path);
    }
	
    /**
     * Create, flash success or error then return the new id for future use
     *
     * @param $class
     * @param $request
     * @param bool|false $imageColumn
     * @param string $path
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function createFlashParentRedirect($class, $request, $imageColumn = false, $path = "index")
    {
        $model = $class::create($this->getData($request, $imageColumn));
        $model->id ? Flash::success(trans('admin.create.success')) : Flash::error(trans('admin.create.fail'));
        return $model->id;
    }

    /**
     * Save, flash success or error then redirect
     *
     * @param $model
     * @param $request
     * @param bool|false $imageColumn
     * @param string $path
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveFlashRedirect($model, $request, $imageColumn = false, $path = "index", $callable = null)
    {
        $model->fill($this->getData($request, $imageColumn));
        $model->save() ? Flash::success(trans('admin.update.success')) : Flash::error(trans('admin.update.fail'));

        if ($callable != null) {
            call_user_func($callable);
        }

        return $this->redirectRoutePath($path);
    }

  /**
   * @param $model
   * @param $request
   * @param $imageColumn
   */
  public function saveFlashParentRedirect($model, $request, $imageColumn = false)
    {
      $model->fill($this->getData($request, $imageColumn));
      $model->save() ? Flash::success(trans('admin.update.success')) : Flash::error(trans('admin.update.fail'));
      return $model->id;
    }

    /**
     * Get data, if image column is passed, upload it
     *
     * @param $request
     * @param $imageColumn
     * @return mixed
     */
    private function getData($request, $imageColumn)
    {
        return $imageColumn === false ? $request->all() : ImageService::uploadImage($request, $imageColumn);
    }

    /**
     * Delete and flash success or fail then redirect to path
     *
     * @param $model
     * @param string $path
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroyFlashRedirect($model, $path = "index")
    {
        $model->delete() ?
            Flash::success(trans('admin.delete.success')) :
            Flash::error(trans('admin.delete.fail'));
        return $this->redirectRoutePath($path);
    }

    /**
     * Returns redirect url path, if error is passed, show it
     *
     * @param string $path
     * @param null $error
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function redirectRoutePath($path = "index", $error = null)
    {
        if ($error) {
            Flash::error(trans($error));
        }
        return redirect($this->urlRoutePath($path));
    }

    /**
     * Inserts or updates as needed, linkinig new record to 
     * parent entity
     *
     * @param array $data
     * @param int $parent_key
     * @return
     */
    public function upsertAll($data, $parent_entity, $parent_key)
    {
        foreach($data as $class => $rows)
        {
            $class = "\\App\\".$class;
            $table = (new $class())->getTable();   
            if (!empty($rows))
            {
                foreach($rows as $child)
                {
                    try {
                        if(empty($child['id']))
                        {
                            $child[$parent_entity] = $parent_key;
                            $new = $class::create($child);
                            $new->save();
                        }
                        else
                        {
                            DB::table($table)->where('id', '=', $child['id'])->update($child);
                        }
                    } catch (Exception $e) {
                            // TODO: Improve exception handling
                    }
                }					
            }
        }
    }
	
    /**
     * Returns full url
     *
     * @param string $path
     * @param bool|false $model
     * @return string
     */
    protected function urlRoutePath($path = "index", $model = false)
    {
        if ($model) {
            return route($this->routePath($path), ['id' => $model->id]);
        } else {
            return route($this->routePath($path));
        }
    }

    /**
     * Returns route path as string
     *
     * @param string $path
     * @return string
     */
    public function routePath($path = "index")
    {
        return 'admin.' . snake_case($this->model) . '.' . $path;
    }

    /**
     * Returns view path for the admin
     *
     * @param string $path
     * @param bool|false $object
     * @return \BladeView|bool|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     */
    public function viewPath($path = "index", $object = false)
    {
        $path = 'admin.' . str_plural(snake_case($this->model))  . '.' . $path;
        if ($object !== false) {
            return view($path, compact('object'));
        } else {
            return $path;
        }
    }

    public function viewPathWithData($path = "index", array $data = array())
    {
        $path = 'admin.' . str_plural(snake_case($this->model))  . '.' . $path;
        return view($path, $data);
    }

    /**
     * Get select list for languages
     *
     * @return mixed
     */
    protected function getSelectList()
    {
        return Language::pluck('title', 'id')->all();
    }

    /**
     * Get model name, if isset the model parameter, then get it, if not then get the class name, strip "Controller" out
     *
     * @return string
     */
    protected function getModel()
    {
        return empty($this->model) ?
            explode('Controller', substr(strrchr(get_class($this), '\\'), 1))[0]  :
            $this->model;
    }

    /**
     * Returns fully class name for form
     *
     * @return string
     */
    protected function getFormPath()
    {
        $model =  title_case(str_plural($this->model));
        return 'App\Forms\Admin\\' . $model . 'Form';
    }
    
    /**
     * Formats the given data for a datatable and returns it back
     * @param Request $request
     * @param object $data Array of objects to return back
     * @param int $total Total amount of results available
     * @param int $filtered Filtered count (defaults to 0)
     */
    protected function dtResponse (Request $request, $data, $total = null, $filtered = 0) {
        $response = (object)[];
        $response->draw = $request->input ("draw");
        $response->recordsTotal = $total ?: count ($data ?: array ());
        $response->recordsFiltered = $filtered ?: $response->recordsTotal;
        $response->data = $data;
        
        return response ()->json ($response);
    }
}
