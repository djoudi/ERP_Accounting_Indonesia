<?php

class sUser extends CActiveRecord
{
	public $default_group_name;
	public $password_repeat;
	public $sso_id;
	public $sso_name;
	
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 's_user';
	}

	public function rules()
	{
		return array(
				array('username, password, default_group, status_id', 'required'),
				array('default_group_name', 'required','on'=>'defaultgroup'),
				array('status_id, default_group, created_date, sso_id', 'numerical', 'integerOnly'=>true),
				array('username, created_by', 'length', 'max'=>25),
				array('password, salt, sso_name, photo_path', 'length', 'max'=>100),
				array('full_name', 'length', 'max'=>50),
				array('last_login,status_id', 'safe'),
				array('username, default_group, status_id,sso_id, photo_path', 'safe', 'on'=>'search'),
				array('password_repeat','compare','compareAttribute'=>'password','on'=>'passwordupdate'),
				array('password, password_repeat', 'required','on'=>'passwordupdate'),
		);
	}

	public function relations()
	{
		return array(
				'organization' => array(self::BELONGS_TO, 'aOrganization', 'default_group'),
				'status' => array(self::HAS_ONE, 'sParameter', array('code'=>'status_id'),'condition'=>'type = "cStatus"'),
				'module' => array(self::HAS_MANY, 'sUserModule', 's_user_id'),
				'group' => array(self::HAS_MANY, 'sGroup', 'parent_id'),
				'right' => array(self::HAS_MANY, 'sAuthassignment', 'userid'),
				'groupCount' => array(self::STAT, 'sGroup', 'parent_id'),
				'moduleCount' => array(self::STAT, 'sUserModule', 's_user_id'),
				'moduleList' => array(self::MANY_MANY, 'sModule','s_user_module(s_user_id,s_module_id)'),
				'groupList' => array(self::MANY_MANY, 'aOrganization', 's_group(parent_id,organization_root_id)'),
		);
	}

	public function attributeLabels()
	{
		return array(
				'id' => 'ID',
				'username' => 'Username',
				'password' => 'Password',
				'salt' => 'Salt',
				'default_group' => 'Default Group',
				'status_id' => 'Status',
				'created_date' => 'Created Date',
				'created_by' => 'Created By',
				'last_login' => 'Last Login',
				'sso_id' => 'SSO ID',
				'photo_path' => 'Photo Path',
		);
	}

	public function search()
	{
		$criteria=new CDbCriteria;
		if (Yii::app()->user->name !="admin") 
			$criteria->addNotInCondition('username',array('admin'));
		
		return new CActiveDataProvider(get_class($this), array(
				'criteria'=>$criteria,
				'pagination'=>array(
					'pageSize'=>30,
				),
				'sort'=>array(
					'defaultOrder'=>'last_login DESC',
				),
		));
	}

	public function searchEntity($id)
	{
		$criteria=new CDbCriteria;
		$criteria->with=array('group');

		$criteria1=new CDbCriteria;
		$criteria->compare('default_group',$id,'OR');
		$criteria1->compare('organization_root_id',$id,'OR');

		return new CActiveDataProvider($this, array(
				'criteria'=>$criteria,
				'pagination'=>array(
						'pageSize'=>100,
				),
		));
	}
	
	
	protected function beforeSave()
	{
		if($this->isNewRecord) {
			$this->salt=$this->generateSalt();
			$this->password=md5($this->salt.$this->password);
			$this->created_by=Yii::app()->user->id;
			$this->created_date=time();
		} else {
			$this->created_by=Yii::app()->user->id;
			$this->created_date=time();
		}
			
		return parent::beforeSave();

	}

	public function generateSalt()
	{
		return uniqid('',true);
	}

	public function validatePassword($password)
	{
		return $this->hashPassword($password,$this->salt)===$this->password;
	}

	public function hashPassword($password,$salt)
	{
		return md5($salt.$password);
	}


	public function allUsers($all='')
	{
		$_items=array();
		$models=$this->findAll(array('order'=>'username'));
		if ($all=='all') {
			self::$_items[0]='All';
		}

		foreach($models as $model)
			self::$_items[$model->id]=$model->username;

		return self::$_items;

	}

	public function findName($id)
	{
		$model=$this->findByPk((int)$id);
		if ($model == null)
			return "All";
			
		return $model->username;

	}


	private static $_items2=array();
	private static $_admin2=array('admin');

	public static function items2($type) {
		if(!isset(self::$_items2[$type]))
			self::loadItems2($type);
		return array_merge(self::$_admin2,self::$_items2[$type]);
	}

	private static function loadItems2($type)
	{
		self::$_items2[$type]=array();
		$models2=self::model()->findAllBySql('SELECT a.id, a.username FROM s_user a
				INNER JOIN s_user_module b ON a.id = b.s_user_id
				WHERE b.s_module_id = "' . $type . '"');
		foreach($models2 as $model2) {
			self::$_items2[$type][$model2->id]=$model2->username;
		}
	}


	private static $_items=array();
	private static $_admin=array('admin');

	public static function items($type) {
		if(!isset(self::$_items[$type]))
			self::loadItems($type);
		return array_merge(self::$_admin,self::$_items[$type]);
	}

	private static function loadItems($type)
	{
		self::$_items[$type]=array();
		$models=self::model()->findAllBySql('SELECT a.id, a.username FROM s_user a
				INNER JOIN s_user_module b ON a.id = b.s_user_id
				WHERE b.s_matrix_id = 5 and b.s_module_id = "' . $type . '"');
		foreach($models as $model) {
			self::$_items[$type][$model->id]=$model->username;
		}
	}


	private static $_items1=array();

	public static function items1($type)
	{
		if(!isset(self::$_items1[$type]))
			self::loadItems1($type);
		return self::$_items1[$type];
	}



	public function getGroup()
	{
		$_group=self::findByPk(Yii::app()->user->id)->default_group;
		return (int)$_group;
	}
	
	public function getGroupMember()
	{
		$_items[]=$this->organization->name;
		
		foreach($this->groupList as $model)
			$_items[]=$model->name;

		return $_items;
	}
	
	public function getModuleMember()
	{
		$_items=array();
		foreach($this->moduleList as $model)
			$_items[]=$model->title;

		return $_items;
	}

	public function getGroupName()
	{
		$findself=self::model()->findByPk(Yii::app()->user->id);
		$defGroup=$findself->default_group;
		
		$model=aOrganization::model()->findByPk($defGroup);
		if ($model !=null) {
			$grName=$model->name;
		} else
			$grName="";
			
		return $grName;
	}

	public function getGroupArray()
	{
		$models=sGroup::model()->findAll('parent_id = '.Yii::app()->user->id);

		//Default Group as the first array
		$_items[]=$this->getGroup();

		foreach($models as $model)
			$_items[]=$model->organization_root_id;

		return $_items;
	}

	public function getGroupNotificationArray()
	{
		$models=sNotificationGroupMember::model()->findAll('user_id = '.Yii::app()->user->id);


		foreach($models as $model)
			$_items[]=$model->parent_id;

		if (isset($_items)) {
			return $_items;
		} else {
			$_items[]='NOT AVAILABLE';
			return $_items;
		}
	}

	public function getGroupRoot()
	{
		$model=self::findByPk((int)Yii::app()->user->id);

		if ($model->organization->parent_id == 0) { //L1
			$_groupRoot=$model->organization->id;
		} elseif ($model->organization->getparent->parent_id == 0) { //L2
			$_groupRoot=$model->organization->getparent->id;
		} elseif ($model->organization->getparent->getparent->parent_id == 0) { //L3
			$_groupRoot=$model->organization->getparent->getparent->id;
		} else  //L4
			$_groupRoot=$model->organization->getparent->getparent->getparent->id;

		return $_groupRoot;
	}

	public function getGroupRootName()
	{
		$model=self::findByPk((int)Yii::app()->user->id);

		if ($model->organization->parent_id == 0) { //L1
			$_groupRoot=$model->organization->name;
		} elseif ($model->organization->getparent->parent_id == 0) { //L2
			$_groupRoot=$model->organization->getparent->name;
		} elseif ($model->organization->getparent->getparent->parent_id == 0) { //L3
			$_groupRoot=$model->organization->getparent->getparent->name;
		} else  //L4
			$_groupRoot=$model->organization->getparent->getparent->getparent->name;

		return $_groupRoot;
	}

	public function getAccess($mid)
	{
		$_items=array();
		$models=self::model()->findAllBySql('SELECT a.id, a.username FROM s_user a
				INNER JOIN s_user_module b ON a.id = b.s_user_id
				WHERE b.s_module_id = ' . $mid );
		$_items[]='admin';

		if ($models != null) {
			foreach($models as $model) {
				$_items[$model->id]=$model->username;
			}
		} else
			$_items[]='non_registered_user';

		return $_items;
	}

	public function getTopCreated() {

		$models=self::model()->findAll(array('limit'=>10,'order'=>'created_date DESC'));

		$returnarray = array();

		foreach ($models as $model) {
			$returnarray[] = array('id' => $model->username, 'label' => $model->username, 'icon'=>'list-alt', 'url' => array('view','id'=>$model->id));
		}

		return $returnarray;
	}

	public function getTopRelated($id) {

		$_related = self::model()->findByPk((int)$id)->name;
		$_exp=explode(" ",$_related);


		$criteria=new CDbCriteria;

		if (isset($_exp[0]))
			$criteria->compare('name',$_exp[0],true,'OR');

		if (isset($_exp[1]))
			$criteria->compare('name',$_exp[1],true,'OR');
			
		$criteria->limit=10;
		$criteria->order='updated_date DESC';

		$models=self::model()->findAll($criteria);

		$returnarray = array();

		foreach ($models as $model) {
			$returnarray[] = array('id' => $model->username, 'label' => $model->username, 'icon'=>'list-alt', 'url' => array('view','id'=>$model->id));
		}

		return $returnarray;
	}

	public function sso() {
		$sso_employee = Yii::app()->db->createCommand()
		->select('employee_name')
		->from('g_person')
		->where('userid=:id', array(':id'=>$this->id))
		->queryScalar();
		
		return $sso_employee;
	}
	
	public function getFullName() {
		$findself=self::model()->findByPk(Yii::app()->user->id);

		if ($findself->full_name == null) {
			$_name=$findself->username;
		} else
			$_name=$findself->full_name;

		return $_name;
	}
	
	public function getPhotoExist() {
		if ($this->photo_path != null) {
			if (is_file(Yii::app()->basePath . "/../shareimages/user/" .$this->photo_path))
				return true;
			else
				return false;
		}
		return false;

	}

	public function getPhotoPath() {
		if ($this->photo_path != null && $this->PhotoExist) {
			$path=CHtml::image(Yii::app()->request->baseUrl . "/shareimages/user/" .$this->photo_path, CHtml::encode($this->getFullName()), array("width"=>"100%",'id'=>'photo'));
		} else 
			$path=CHtml::image(Yii::app()->request->baseUrl . "/shareimages/nophoto.jpg", CHtml::encode($this->getFullName()), array("width"=>"100%",'id'=>'photo'));
		
		return $path;

	}

	public function userRight($id) {
		$rawData=Yii::app()->db->createCommand('SELECT * FROM s_authassignment where userid = '.$id)->queryAll();

		$dataProvider=new CArrayDataProvider($rawData, array(
    		'keyField'=>'itemname',
    		'sort'=>array(
	        	'attributes'=>array(
   		         	'itemname',
  	    	  	),
    		),
    		'pagination'=>array(
        		'pageSize'=>10,
    		),
		));
		
		return $dataProvider;	
	}	

}