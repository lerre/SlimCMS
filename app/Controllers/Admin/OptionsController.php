<?php

namespace App\Controllers\Admin;

use App\Helpers\SessionManager as Session;
use \Illuminate\Database\Capsule\Manager as Schema;
use \Psr\Http\Message\ServerRequestInterface as request;
use App\Source\Factory\ModelsFactory;
use App\Source\ModelFieldBuilder\BuildFields;

class OptionsController extends UniversalController
{
	public function index(request $req, $res){
		$this->initRoute($req);

		$model = ModelsFactory::getModelWithRequest($req);

		if( !$this->containerSlim->systemOptions->isHideFunctionality() ||
			$this->containerSlim->systemOptions->isDevMode() )
			$this->data['items'] = $model->paginate($this->pagecount);
		elseif( $this->containerSlim->systemOptions->isHideFunctionality() )
			$this->data['items'] = $model->where('frozen', '!=', 1)->orWhere('code', 'develop_mode')->paginate($this->pagecount);

		$this->data['items']->setPath($this->router->pathFor($this->data['all_e_link']));
		$t = $model->getColumnsNames(['GroupName']);
		$this->data['fields'] = $this->getFields($t, ['id'], ['values', 'type', 'options_group_id', 'frozen']);

		$this->view->render($res, 'admin\optionsTable.twig', $this->data);
	}

	public function add(request $req, $res){
		$this->initRoute($req);

		$model = ModelsFactory::getModelWithRequest($req);
		$builder = new BuildFields();
		$builder->setFields($model->getColumnsNames())->addJsonShema($model->getAnnotations())->build();
		$builder->setType('options_group_id', 'select');
		$model = ModelsFactory::getModel('GroupOptions');
		foreach ($model->where('active', 1)->get() as $item) {
			$builder->getField('options_group_id')->values[$item->id] = $item->name;
		}
		$builder->getField('value')->noVisible();

		$this->data['ttt'] = $builder->getAll();

		$this->view->render($res, 'admin\addTables.twig', $this->data);
	}

	public function edit(request $req, $res, $args){
		$this->initRoute($req);

		$model = ModelsFactory::getModelWithRequest($req);
		$this->data['fields'] = $this->getFields($model->getColumnsNames(), ['id']);
		$this->data['fieldsValues'] = $model->find($args['id']);
		$this->data['type_link'] = $this->data['save_link'];

		if( $this->data['fieldsValues']['frozen'] && 
			(  !$this->containerSlim->systemOptions->isDevMode() &&
				$this->data['fieldsValues']['code']!='develop_mode') ){
			$this->flash->addMessage('errors', $this->controllerName.' this value not editable, set developers mode.');
			return $res->withStatus(302)->withHeader('Location', $this->router->pathFor('list.'.$this->controllerName));
		}

		$builder = new BuildFields();
		$builder->setFields($model->getColumnsNames())->addJsonShema($model->getAnnotations());
		$builder->build();
		$builder->setType('id', 'hidden');
		$builder->setType('options_group_id', 'select');
		$builder->setType('value', $this->data['fieldsValues']->type);
		if( in_array($this->data['fieldsValues']->type, ['select', 'multiselect', 'checkbox', 'radio']) && $this->data['fieldsValues']->values )
			$builder->getField('value')->values = json_decode($this->data['fieldsValues']->values);
		$model = ModelsFactory::getModel('GroupOptions');
		foreach ($model->where('active', 1)->get() as $item) {
			$builder->getField('options_group_id')->values[$item->id] = $item->name;
		}
		foreach ($this->data['fields'] as $name) {
			$builder->getField($name)->setValue($this->data['fieldsValues']->$name);
		}

		if( $this->containerSlim->systemOptions->isHideFunctionality() ){
			$builder->getField('values')->noVisible();
			$builder->getField('type')->noVisible();
			$builder->getField('frozen')->noVisible();
		}
		if( $this->containerSlim->systemOptions->isDevMode() && 
			!$this->data['fieldsValues']->frozen ){
			$builder->getField('values')->noVisible(false);
			$builder->getField('type')->noVisible(false);
		}

		$this->data['ttt'] = $builder->getAll();

		$this->view->render($res, 'admin\addTables.twig', $this->data);
	}
}
