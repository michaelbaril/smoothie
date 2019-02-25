<?php

namespace Baril\Smoothie\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class ShowTreeCommand extends Command
{
    protected $signature = 'smoothie:show-tree {model : The model class.}
        {--label= : The property to use as label.}
        {--depth= : The depth limit.}';
    protected $description = 'Outputs the content of the table in tree form';

    protected $model;
    protected $label;
    protected $currentDepth = 0;

    public function handle()
    {
        $model = $this->input->getArgument('model');
        if (!class_exists($model) || !is_subclass_of($model, Model::class) || !method_exists($model, 'getClosureTable')) {
            $this->error('{model} must be a valid model class and use the BelongsToTree trait!');
            return;
        }

        $this->model = $model;
        $this->label = $this->input->getOption('label');

        $this->showTree($this->input->getOption('depth'));
    }

    protected function showTree($depth)
    {
        $tree = $this->model::getTree($depth);
        foreach ($tree as $node) {
            $this->showNode($node);
        }
    }

    protected function showNode($node)
    {
        $line = str_repeat(' ', 2 * $this->currentDepth) . '- <info>#' . $node->getKey() . '</info>';
        if ($this->label) {
            $line .= ': ' . $node->{$this->label};
        }
        $this->line($line);
        if ($node->relationLoaded('children')) {
            $this->currentDepth++;
            foreach ($node->children as $child) {
                $this->showNode($child);
            }
            $this->currentDepth--;
        }
    }
}
