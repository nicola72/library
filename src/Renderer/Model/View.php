<?php

namespace Renderer\Model;

use Utils\Log;

class View
{
    protected $template;
    protected $variables = [];
    
    public $terminal = false;

    protected $name = 'content';

    /**
     * @var array View
     */
    protected $children = [];

    public function __construct()
    {
    }

    public function setTemplate($template)
    {
        $this->template = (string) $template;
        return $this;
    }

    public function setTerminal($terminal)
    {
        $this->terminal = (bool) $terminal;
        return $this;
    }

    public function setVariable($name, $value)
    {
        $this->variables[(string) $name] = $value;
        return $this;
    }

    public function setVariables($variables)
    {
        if (! is_array($variables))
        {
            $message = 'Errore! la funzione View->SetVariables() si aspetta un array';
            Log::warn('errore_esecuzione_applicazione', $message);

        }

        foreach ($variables as $key => $value)
        {
            $this->setVariable($key, $value);
        }

        return $this;
    }

    public function getVariable($name, $default = null)
    {
        $name = (string) $name;
        if (array_key_exists($name, $this->variables))
        {
            return $this->variables[$name];
        }

        return $default;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function addChild(View $child, $name = null)
    {
        $this->children[] = $child;
        if (null !== $name)
        {
            $child->setName($name);
        }

        return $this;
    }

    /**
     * @return array View
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getName()
    {
        return $this->name;
    }
}