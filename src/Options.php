<?php

namespace PharBuilder;

class Options
{
    protected $argv;
    protected $_single = [];
    protected $_required = [];
    protected $_longSingle = [];
    protected $_longRequired = [];

    protected $command;
    protected $options;

    public function __construct(array $argv)
    {
        $this->argv = $argv;
    }

    public function single($short, $long = []){
        $this->_single = $short;
        $this->_longSingle = $long;
    }

    public function required($short, $long = []){
        $this->_required = $short;
        $this->_longRequired = $long;
    }

    public function parse(){
        $command = [];
        $options = [];

        $iterator = new \ArrayIterator($this->argv);
        $iterator->rewind();

        foreach ($iterator as $item){
            $matches = null;
            if(preg_match("/--?([\w\-1-9]+)/", $item, $matches)){
                $found = $matches[1];
                if(in_array($found, $this->_longRequired) || in_array($found, $this->_required)){
                    $iterator->next();
                    $options[$found] = $iterator->current();
                    continue;
                }else if(in_array($found, $this->_longSingle)){
                    $options[$found] = true;
                    continue;
                }

                foreach (str_split($found) as $char){
                    if(in_array($char, $this->_single))
                        $options[$char] = isset($options[$char]) ? $options[$char] + 1 : 1;
                }
            }else{
                $command[] = $item;
            }
        }

        $this->command = $command;
        $this->options = $options;
    }

    public function getCommand(){
        return $this->command;
    }

    public function getOptions(){
        return $this->options;
    }
}