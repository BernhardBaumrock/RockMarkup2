<?php namespace ProcessWire;
/** @var WireFileTools $this */
$this->addHookBefore(
  "InputfieldForm(name=example)::render",
  function(HookEvent $event) {
    $form = $event->object; /** @var InputfieldForm $form */
    $name = $this->input->get('name', 'text');
    if($name != '04-events') return;

    // add dummy inputfield
    $f = $this->modules->get('InputfieldMarkup');
    $f->label = "Dummy";
    $f->value = "Toggle me and watch events in the console";
    $form->insertAfter($f, $form->getChildByName('04-events'));
  }
);