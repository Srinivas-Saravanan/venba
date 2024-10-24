<?php

namespace App\Controllers;
use App\Models\UsersModel;
use App\Models\FamilyModel;
use App\Models\RuleModel;
use DateTime;

class Auth extends BaseController
{
    public function index()
    {
        
        if (session()->has('loggedUser') || session()->has('loggedAdmin')){
            $filter = 'self';
            return redirect()->to(base_url('home/home'));
        }
        elseif(session()->has('loggedEmp')){
            $model = new FamilyModel();
            $username = session()->get('loggedEmp');
            $code = $model->select('familyCode')->where('name',$username)->first();
            $familyCode = (int)$code['familyCode'];

            return redirect()->to(base_url('home/view/'.esc($familyCode)));
        }
        
        return view('/login');
    }
    public function check() {        
        $validation = $this->validate([
            'username' => [
                'rules' => 'required|is_not_unique[users.username]',
                'errors' => [
                    'required' => 'Username cannot be empty',
                    'is_not_unique' => 'Username does not exist'
                ]
            ],
            'password' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Password cannot be empty'
                ]
            ]
        ]);
    
        if (!$validation) {
            return view('/login', ['validation' => $this->validator]);
        }
    
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');
        $UsersModel = new UsersModel();
        $user_info = $UsersModel->where('username', $username)->first();
    
        if (!$user_info) {
            session()->setFlashdata('fail', 'Username does not exist');
            return redirect()->to('/login')->withInput(); // Redirect to login explicitly
        }
    
        if ($password != $user_info['password']) {
            session()->setFlashdata('fail', 'Incorrect Password');
            return redirect()->to('/login')->withInput(); 
            
        }
    else{
        if($user_info['role'] === 'admin'){
            session()->set('loggedAdmin', $user_info['username']);
            $filter = 'self';
            return redirect()->to(base_url('home/home'));
        }
        elseif($user_info['role'] === 'user'){
            session()->set('loggedUser', $user_info['username']);
            $filter = 'self';
            return redirect()->to(base_url('home/home'));

        }
        elseif($user_info['role'] === 'employee'){
            session()->set('loggedEmp', $user_info['username']);
            $model = new FamilyModel();
            $username = session()->get('loggedEmp');
            $code = $model->select('familyCode')->where('name',$username)->first();
            $familyCode = (int)$code['familyCode'];
            return redirect()->to(base_url('home/view/'.esc($familyCode)));
            
        }
        
    }
    }
    public function home($filter = null) {
        $model = new FamilyModel();
    
        $name = $this->request->getPost('name');
        $gender = $this->request->getPost('gender');
        $familyCode = $this->request->getPost('familyCode');
        $relationship = $this->request->getPost('relationship');
    
        $query = $model->select('*');
    
        if (!empty($name) || !empty($gender) || !empty($familyCode) || (!empty($relationship) && $relationship !== 'Select')) {
            if (!empty($name)) {
                $query->like('name', $name);
            }
    
            if (!empty($gender) && $gender !== 'Select') {
                $query->where('gender', $gender);
            }
    
            if (!empty($familyCode)) {
                $query->where('familyCode', $familyCode);
            }
    
            if (!empty($relationship) && $relationship !== 'Select') {
                if($relationship == 'parents'){
                    $query->whereIn('relationship',['mother','father']);
                }
                elseif($relationship == 'parentInLaw'){
                    $query->whereIn('relationship',['motherInLaw','fatherInLaw']);
                }
                elseif($relationship == 'elders'){
                    $query->whereIn('relationship',['motherInLaw','fatherInLaw','mother','father']);
                }
                else{
                    $query->where('relationship',$relationship);
                }
            }
        } else {
            $query->where('relationship', 'self');
        }
    
        $records['familyhead'] = $query->orderBy('familyCode', 'ASC')->findAll();
    
        return view('home/home', ['records' => $records]);
    }
    
    

    public function logout(){
        
        if (session()->has('loggedAdmin')){
            session()->remove('loggedAdmin');
            session()->setFlashdata('fail','Admin Logged Out');
            return redirect()->to(base_url('/'));
        }
        elseif(session()->has('loggedUser')){
            session()->remove('loggedUser');
            session()->setFlashdata('fail','User Logged Out');
            return redirect()->to(base_url('/'));
        }
        elseif(session()->has('loggedEmp')){
            session()->remove('loggedEmp');
            session()->setFlashdata('fail','Employee Logged Out');
            return redirect()->to(base_url('/'));
        }
        return redirect()->to(base_url('/'));
    }

    public function view($familyCode){
        $rec = new FamilyModel();
        // $username = session()->get('loggedEmp');
        //     $code = $rec->select('familyCode')->where('name',$username)->first();
        //     $familyCode = (int)$code['familyCode'];

        
        $records['familyHead'] = $rec->where('relationship','self')->where('familyCode',$familyCode)->first();
        $records['allMembers'] = $rec->where('familyCode',$familyCode)->orderBy('relationship = \'self\' DESC')->findAll();    
        
        return view('home/view',$records);
    }

    public function editAdd($familyCode, $name = null)
    {
        $function = $this->request->getGet('function');
        $function2 = $this->request->getGet('function2');
    
        if ($function) {
            $fun = ['add' => $function];
        } else {
            $fun = ['edit' => $function2];
        }
    
        $fun['familyCode'] = $familyCode;
        $fun['name'] = $name;
        $model = new FamilyModel();
        $result4 = $model->where('familyCode', $familyCode)->where('relationship', 'self')->first();
        $result = $model->select('relationship')->where('name', $name)->first();
        $result2 = $model->select('gender')->where('name', $name)->first();
        $result3 = $model->select('dob')->where('name', $name)->first();
        $exsitingRelation = $model->select('relationship')->where('familyCode',$familyCode)->findAll();

        $fun['name2'] = [
            'gender' => $result2['gender']?? null,
            'dob' => $result3['dob']?? null,
            'relationship' => $result['relationship']?? null,
            'selfExist' =>$result4,
            'existingRelation' =>$exsitingRelation
        ];
        // var_dump($fun['name2']['existingRelation']);
        // die();
        $ruleModel = new RuleModel();
        $ruleTable = $ruleModel->where('familyCode', $familyCode)->findAll();
        

        if (empty($ruleTable)) {
            return $this->response->setJSON(['error' => 'No rules found for this family code.']);
        }
        
        $rules = json_decode($ruleTable[0]['rules'], true);

        $data = array_merge($fun, ['rules' => json_encode($rules)]);


        return view('home/editAdd', $data);
    }
    public function valid(){
        $ruleModel = new RuleModel();
        $familyCode = $this->request->getGet('familyCode');
        $ruleTable = $ruleModel->where('familyCode', $familyCode)->findAll();
        $modelss = new FamilyModel();
        $selfBorn = $modelss->select('dob')->where('familyCode',$familyCode)->where('relationship','self')->first();
        $selfg = $modelss->select('gender')->where('familyCode',$familyCode)->where('relationship','self')->first();
        $selfGender = $selfg['gender'];
        $selfDob = new DateTime($selfBorn['dob']);
        $today = new DateTime('today');
        $selfAge = $selfDob->diff($today)->y;
        
        if (empty($ruleTable)) {
            return $this->response->setJSON(['error' => 'No rules found for this family code.']);
        }
        

        $rules = json_decode($ruleTable[0]['rules'], true);
        $relationship = $this->request->getGet('relationship');
        $ruleFor =  $rules[$relationship];

        $minAge = $ruleFor['minAge'];
        $maxAge = $ruleFor['maxAge'];

        $ageRule = [$minAge,$maxAge,$selfAge,$selfGender];

        return $this->response->setJSON(['status'=>'success','rules'=>$ageRule]);

    }
    public function edAdder($familyCode,$name = null){

        $model = new FamilyModel();

        $function = $this->request->getPost('function');

        $data = [
            'name' =>$this->request->getPost('name'),
            'gender' =>$this->request->getPost('gender'),
            'dob' =>$this->request->getPost('dob'),
            'relationship' =>$this->request->getPost('relationship'),
            'familyCode' =>$familyCode
        ];
        if(strtolower($function) == 'edit'){
            $insertfun = $model->set($data)->where('familyCode',$familyCode)->where('name',$name)->update();
        }
        else{
            $addRow = $model->insert($data);
        }
    
        return redirect()->to(base_url('home/view/'.$familyCode));
    }


    public function delete($familyCode,$name){

        $model = new FamilyModel();

        $deleteRow = $model->where('familyCode',$familyCode)->where('name',$name)->delete();

        return redirect()->to(base_url('home/view/'.$familyCode));
    }
    public function rules() {
        $model = new RuleModel();
        $familyModel = new FamilyModel();
        $results = $model->select('family_rules.familyCode, family_list.name')
                 ->join('family_list', 'family_list.familyCode = family_rules.familyCode')
                 ->where('family_list.relationship','self')
                 ->findAll();

        $result = ['result'=>$results];
        return view('home/rules', $result);
    }
    
    public function ruler($familyCode) {
        $model = new RuleModel();
        $ruleTable = $model->select('rules')->where('familyCode', $familyCode)->findAll();
        $rules = [];
    
        if (isset($ruleTable[0])) {
            $rules = json_decode($ruleTable[0]['rules'], true);
        }
    
        $rules['familyCode'] = $familyCode;
    
        return view('home/ruler', $rules);
    }
    
public function saveRules(){

    $selfAllowed = $this->request->getPost('self');
    $selfAllowed = isset($selfAllowed)?true:false;
    $spouseAllowed = $this->request->getPost('spouse');
    $spouseAllowed = isset($spouseAllowed)?true:false;
    $childrenCount = $this->request->getPost('child');
    $elders = $this->request->getPost('elders');
    $childAllow = $childrenCount>0?true:false;
    $familyCode = $this->request->getPost('familyCode');
    
    if ($elders == '1p'){
        $pallowed = true;
        $pilallowed = null;
        $pcount = 1;
        $pilcount = 0;
        $parentsAllowed = 1;
        $parentInLawAllowed = null;
        $cross = 0;
    }elseif($elders == '2p'){
        $pallowed = true;
        $pilallowed = null;
        $pcount = 1;
        $pilcount = 0;
        $parentsAllowed = 2;
        $parentInLawAllowed = null;
        $cross = 0;
    }
    elseif($elders == '1pil'){
        $pallowed = null;
        $pilallowed = true;
        $pcount = 0;
        $pilcount = 1;
        $parentsAllowed = null;
        $parentInLawAllowed = 1;
        $cross = 0;
    }elseif($elders == '2pil'){
        $pallowed = null;
        $pilallowed = true;
        $pcount = 0;
        $pilcount = 1;
        $parentsAllowed = null;
        $parentInLawAllowed = 2;
        $cross = 0;
    }elseif($elders == 'either'){
        $pallowed = true;
        $pilallowed = true;
        $pcount = 1;
        $pilcount = 1;
        $parentsAllowed = 2;
        $parentInLawAllowed = 2;
        $cross = 1;
    }elseif($elders == 'any2'){
        $pallowed = true;
        $pilallowed = true;
        $pcount = 1;
        $pilcount = 1;
        $parentsAllowed = 2;
        $parentInLawAllowed = 2;
        $cross = 2;
    }elseif($elders == 'all'){
        $pallowed = true;
        $pilallowed = true;
        $pcount = 1;
        $pilcount = 1;
        $parentsAllowed = 2;
        $parentInLawAllowed = 2;
        $cross = 3;
    }
    

    $model = new RuleModel();

    $rules = ["self" => ["allowed" => $selfAllowed, "minAge" => 18, "maxAge" => 60, "age_limitBelow" => null, "age_limitAbove" => true, "allowedMembers" => null],
        "spouse" => ["allowed" => $spouseAllowed, "minAge" => 18, "maxAge" => 60, "age_limitBelow" => null, "age_limitAbove" => true, "allowedMembers" => 1],
        "children" => ["allowed" => $childAllow, "minAge" => 0, "maxAge" => 25, "age_limitBelow" => true, "age_limitAbove" => true, "allowedMembers" => $childrenCount],
        "motherInLaw" => ["allowed" => $pilallowed, "minAge" => 36, "maxAge" => 85, "age_limitBelow" => null, "age_limitAbove" => true, "allowedMembers" => $pilcount],
        "fatherInLaw" => ["allowed" => $pilallowed, "minAge" => 36, "maxAge" => 85, "age_limitBelow" => null, "age_limitAbove" => true, "allowedMembers" => $pilcount],
        "mother" => ["allowed" => $pallowed, "minAge" => 36, "maxAge" => 85, "age_limitBelow" => false, "age_limitAbove" => true, "allowedMembers" => $pcount],
        "father" => ["allowed" => $pallowed, "minAge" => 36, "maxAge" => 85, "age_limitBelow" => false, "age_limitAbove" => true, "allowedMembers" => $pcount],
        "allowedElders" => ["parentsAllowed" => $parentsAllowed, "parentInLawAllowed" => $parentInLawAllowed, "cross" => $cross]];
    // var_dump($rules);
    // die();
    $jsonRules = json_encode($rules);
    // var_dump($jsonRules);
    
    
    $update = $model->where('familyCode', $familyCode)->set('rules', $jsonRules)->update();
    var_dump($update);
    
    if ($update){
        
        session()->setFlashdata('alert', 'Update successful!');
        return redirect()->to(base_url('home/ruler/'.esc($familyCode)));
    }else{
        session()->setFlashdata('alert', 'Update failed. No data was updated.');
        return redirect()->to(base_url('home/ruler/'.esc($familyCode)))->with('error','update failed');
    }
}
public function dashboard(){

    $model = new FamilyModel();
    $self = $model->select('name')->where('relationship','self')->findAll();
    $elders = $model->select('name')->whereIn('relationship',['mother','father','motherInLaw','fatherInLaw'])->findAll();
    $childrens = $model->where('relationship','children')->findAll();
    $parents = $model->select('name')->whereIn('relationship',['mother','father'])->findAll();
    $parentInLaw = $model->select('name')->whereIn('relationship',['motherInLaw','fatherInLaw'])->findAll();
    $spouse = $model->select('name')->where('relationship','spouse')->findAll();
    $data = [
        'self'=>$self,
        'elders'=>$elders,
        'childrens'=>$childrens,
        'parents'=>$parents,
        'parentInLaw'=>$parentInLaw,
        'spouses'=>$spouse
    ];
    // echo count($data['elders']);
    // die();
    return view('home/dashboard2',$data);
}
    public function newFam(){

        return view('home/newFam');
    }

    public function valFam()
{
    $familyCode = $this->request->getPost('familyCode');
    $model = new FamilyModel();
    if ($familyCode) {
        $exists = $model->where('familyCode', $familyCode)->countAllResults() > 0;
        return $this->response->setJSON(['exists' => $exists]);
    }

}
public function saveFam() {
    $model = new FamilyModel();
    $model2 = new RuleModel();
    
    $name = $this->request->getPost('name');
    $gender = $this->request->getPost('gender');
    $dob = $this->request->getPost('dob');
    $familyCode = (int)$this->request->getPost('familyCode');
    $relationship = 'self';

    $data = [
        'name' => $name,
        'gender' => $gender,
        'dob' => $dob,
        'familyCode' => $familyCode,
        'relationship' => $relationship
    ];
    $data2 = [
        'familyCode' => (int)$familyCode,
        'rules' => json_encode([])
    ];

    $insertData = $model->insert($data);


    $insertRules = $model2->insert($data2);
    
    $user = new UsersModel();
    $password = 123;
    $role = 'employee';
    $data3 =[
        'username' => $name,
        'password'=>$password,
        'role'=>$role
    ];
    // var_dump($data3['']);
    // die();
    $newUser = $user->insert($data3);

    
    return redirect()->to(base_url('home/ruler/' . esc($familyCode)))
                     ->with('success', 'Family added successfully');
}
}
