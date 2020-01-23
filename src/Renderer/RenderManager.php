<?php
namespace Renderer;

use Renderer\Model\View;

class RenderManager
{
    protected $vars = [];
    protected $template = null;
    protected $view_folder;
    protected $app_folder;
    protected $content;

    public $app;

    public function __construct($folder, $app)
    {
        $this->app_folder = $folder;
        $this->view_folder = $folder.'views/';
        $this->app = $app;
    }


    public function render(View $view, View $layout = null)
    {
        //se la vista è inglobata nel layout (quindi non ajax)
        if($layout != false)
        {
            $this->setProperties($layout->getVariables());
            $this->setProperties($view->getVariables());


            //i parziali della vista
            $children = $view->getChildren();
            //se la vista ha dei parziali
            if(!empty($children))
            {
                foreach($children as $child)
                {
                    //prendo il buffer del parziale
                    $child_content = $this->getPartial($child->getTemplate(),$child->getVariables());

                    //creo la prop per il RenderManager col nome del parziale e il buffer
                    $this->setProp($child->getName(),$child_content);
                }
            }

            //il file dove si trova la vista
            $view_template = $view->getTemplate();
            if($view_template == '')
            {
                $request = $this->app->getRequest();
                $view_template = 'pages/'.$request->controller.'_'.$request->action;
            }


            //faccio il buffer della vista e lo memorizzo
            try
            {
                ob_start();
                $inc = include $_SERVER['DOCUMENT_ROOT'].$this->view_folder.$view_template.'.phtml';
                $view_content = ob_get_clean();
            }
            catch(\Exception $ex)
            {
                ob_end_clean();
                throw $ex;
            }

            if ($inc === false)
            {
                echo sprintf('Unable to render template "%s"; file include failed',$view_template);
                exit();
            }

            //creo la proprietà col nome della vista e col suo buffer
            $this->setProp($view->getName(),$view_content);

            //i parziali della vista
            $children = $layout->getChildren();
            //se la vista ha dei parziali
            if(!empty($children))
            {
                foreach($children as $child)
                {
                    //prendo il buffer del parziale
                    $child_content = $this->getPartial($child->getTemplate(),$child->getVariables());

                    //creo la prop per il RenderManager col nome del parziale e il buffer
                    $this->setProp($child->getName(),$child_content);
                }
            }

            //il file dove si trova il layout
            $layout_template = $layout->getTemplate();

            //faccio il buffer del layout e lo memorizzo nella prorietà $this->content
            try
            {
                ob_start();
                $app = $this->app;
                $inc = include $_SERVER['DOCUMENT_ROOT'].$this->view_folder.$layout_template.'.phtml';
                $this->content = ob_get_clean();
            }
            catch(\Exception $ex)
            {
                ob_end_clean();
                throw $ex;
            }

            if ($inc === false && empty($this->content))
            {
                echo sprintf('Unable to render template "%s"; file include failed',$view_template);
                exit();
            }

        }
        //nel caso devo renderizzare solo la vista (es. ajax)
        else
        {
            $this->setProperties($view->getVariables());

            $children = $view->getChildren();
            if(!empty($children))
            {
                foreach($children as $child)
                {
                    $child_content = $this->getPartial($child->getTemplate(),$child->getVariables());

                    $this->setProp($child->getName(),$child_content);
                }
            }

            $view_template = $view->getTemplate();
            if($view_template == '')
            {
                $request = $this->app->getRequest();
                $view_template = 'pages/'.$request->controller.'_'.$request->action;
            }
            try
            {
                ob_start();
                
                $inc = include $_SERVER['DOCUMENT_ROOT'].$this->view_folder.$view_template.'.phtml';
                $this->content = ob_get_clean();
            }
            catch(\Exception $ex)
            {
                ob_end_clean();
                throw $ex;
            }

            if ($inc === false)
            {
                echo sprintf('Unable to render template "%s"; file include failed',$view_template);
                exit();
            }

        }

        return $this->content;

    }

    protected function setProperties($vars)
    {
        if(is_array($vars))
        {
            if(count($vars) > 0)
            {
                foreach($vars as $key => $value)
                {
                    $this->{$key} = $value;
                }
                return $this;
            }
        }
        return $this;
    }

    protected function setProp($name,$value)
    {
        $this->{$name} = $value;
    }

    public function getPartial($template, $params = [])
    {
        if(is_array($params) && count($params) > 0)
        {
            foreach($params as $key => $value)
            {
                $this->{$key} = $value;
            }
        }

        try
        {
            ob_start();
            $inc = include $_SERVER['DOCUMENT_ROOT'].$this->view_folder.$template.'.phtml';
            $content = ob_get_clean();
        }
        catch(\Exception $ex)
        {
            ob_end_clean();
            throw $ex;
        }
        if ($inc === false)
        {
            echo sprintf('Unable to render template "%s"; file include failed',$template);
            exit();
        }
        return $content;
    }

    public function getLogo($alt,$estensione = 'png')
    {
        $logo_path = $_SERVER['DOCUMENT_ROOT'] .$this->app_folder.'img/' . strtolower(str_replace(" ", "_", $alt)) . '.' . $estensione;
        if(file_exists($logo_path))
        {
            return strtolower(str_replace(" ", "_", $alt)) . '.' . $estensione;
        }
        else
        {
            return $this->app_folder.'img/logo.png';
        }
    }

    public function getTemplate()
    {
        return $this->template;
    }


}