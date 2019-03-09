<?php
/**
 * 用户管理
 *
 * @author  Alan
 * @date    2016-11-17 15:25:26
 *
 */

class UserModel
{
    /**
     * 数据库类实例
     *
     * @var object
     */
    public $dbh = null;

    public $mch = null;

    /**
     * Constructor
     */
    public function __construct($dbh, $mch = null)
    {
        $this->dbh = $dbh;
        $this->mch = $mch;
    }

    /**
     * 查询用户列表
     */
    public function getSystemUser($params)
    {
        $filter = array();
        if (isset($params['username']) && $params['username'] != '') {
            $filter[] = " `username` LIKE '%".$params['username']."%' ";
        }
        if (isset($params['realname']) && $params['realname'] != '') {
            $filter[] = " `realname` LIKE '%".$params['realname']."%' ";
        }
        if (isset($params['status']) && $params['status'] != '') {
            $filter[] = " `status` = ".$params['status']." ";
        }
        $rows=$params['pageSize'];
        $where =" WHERE `isdel` = 0 ";
        if (1 <= count($filter)) {
            $where .= "AND ". implode(' AND ', $filter);
        }

        $result=array('totalRow'=>0,'totalPage'=>0,'list'=>array());

        $sql = "SELECT count(`sysno`) FROM `concap_user` {$where}";
        $result['totalRow']=$this->dbh->select_one($sql);

        $result['totalPage'] = ceil($result['totalRow'] / $rows);

        $this->dbh->set_page_num($params['pageCurrent']);
        $this->dbh->set_page_rows($params['pageSize']);

        $sql = "SELECT * FROM `concap_user` ".$where;
        $result['list'] = $this->dbh->select_page($sql);
        
        return $result;
    }


    /**
     * 添加用户
     * @author Alan
     * @time 2016-11-14 15:21:32
     */
    public function addUser($params,$privileges = "")
    {
        $result = $this->onlyOne($params);
        if (!empty($result)) {
            return 'existence';
            exit;
        }
        $this->dbh->begin();
        try{
            $res = $this->dbh->insert('concap_user', $params);

            if (!$res) {
                $this->dbh->rollback();
                return false;
            }

            $id = $res;
            $res = $this->dbh->delete('concap_user_r_role', 'user_sysno=' . intval($id));

            if (!$res) {
                $this->dbh->rollback();
                return false;
            }

            if ($privileges !== "") {
                if (!empty($privileges)) {
                    foreach ($privileges as $value) {
                        $privilegesdata = array(
                            'user_sysno' => $id,
                            'role_sysno' => $value,
                        );
                        //concap_role_r_privilege insert
                        $res = $this->dbh->insert('concap_user_r_role', $privilegesdata);

                        if (!$res) {
                            $this->dbh->rollback();
                            return false;
                        }
                    }

                }
            }

            $this->dbh->commit();
            return $id;

        } catch (Exception $e) {
            $this->dbh->rollback();
            return false;
        }
    }

    public  function getUserById($id = 0)
    {
        $sql = "select p.* from concap_user p where sysno = $id ";

        return $this->dbh->select_row($sql);
    }

    /**
     * 角色对应角色 BY 图
     */
    public function roleList()
    {
        $sql = "select * from concap_role WHERE status =1 AND isdel =0";
        return $this->dbh->select($sql);
    }

    public function updateUser($id = 0, $data = array(), $privileges = "")
    {
        $this->dbh->begin();
        try {
            $res = $this->dbh->update('concap_user', $data, 'sysno=' . intval($id));

            if (!$res) {
                $this->dbh->rollback();
                return false;
            }

            $res = $this->dbh->delete('concap_user_r_role', 'user_sysno=' . intval($id));

            if (!$res) {
                $this->dbh->rollback();
                return false;
            }

            if ($privileges !== "") {
                if (!empty($privileges)) {
                    foreach ($privileges as $value) {
                        $privilegesdata = array(
                            'user_sysno' => $id,
                            'role_sysno' => $value,
                        );
                        //concap_role_r_privilege insert
                        $res = $this->dbh->insert('concap_user_r_role', $privilegesdata);

                        if (!$res) {
                            $this->dbh->rollback();
                            return false;
                        }
                    }

                }
            }

            $this->dbh->commit();
            return true;

        } catch (Exception $e) {
            $this->dbh->rollback();
            return false;
        }
    }

    public function updateUserPassword($id = 0, $data = array())
    {
        $this->dbh->begin();
        try {
            //concap_role update
            $res = $this->dbh->update('concap_user', $data, 'sysno=' . intval($id));

            if (!$res) {
                $this->dbh->rollback();
                return false;
            }

            $this->dbh->commit();
            return true;

        } catch (Exception $e) {
            $this->dbh->rollback();
            return false;
        }
    }

    // 根据 id 获取 用户已经绑定的角色
    public function getUserPrivilege($id)
    {
        $sql = "SELECT * FROM `concap_user_r_role` WHERE user_sysno = $id";
        return $this->dbh->select($sql);
    }


    /**
     * 用户登陆校验
     * @author Alan
     * @time 2016-11-15 13:48:17
     **/
    public function UserLogin($params)
    {
        $sql = "select * from concap_user u where `username` = '".$params['username']."'";

        $row = $this->dbh->select_row($sql);

        if(is_array($row) && count($row) > 0){
            $hash = $row['userpwd'];
            if (password_verify($params['userpwd'], $hash)) {
                return $row;
            }else {
                return false;
            }
        }else
            return false;
    }

    /**
     * 登陆成功 更改IP
     **/

    public function setUserInfo($params,$id)
    {
        return $this->dbh->update('concap_user',$params,'sysno=' . intval($id));
    }

    /**
     * 删除用户
    **/
    public function delUser($id)
    {
        $params['isdel'] = 1;
        // $params['status'] = 2;
        return $this->dbh->update('concap_user',$params,'sysno=' . intval($id));
    }


    /**
     * 检测账号唯一性
    **/

    public function onlyOne($params)
    {

        $filter = [];
        if (isset($params['username']) && $params['username'] !='' ) {
            $filter[] = " `username` =  '{$params['username']}' ";
        }
        // if (isset($params['realname']) && $params['realname'] !='' ) {
        //     $filter[] = " `realname` =  '{$params['realname']}' ";
        // }

        $where = ' isdel =0 AND ';
        $where .= implode(' OR ', $filter); 
        $sql =  "SELECT sysno FROM concap_user WHERE $where";

        $data = $this->dbh->select_one($sql);

        return $data;
    }

    //检测用户是否存在
    public function checkUser($username)
    {
        $where = " isdel = 0 AND `username` =  '".$username."'";
        $sql =  "SELECT sysno FROM concap_user WHERE $where";

        $data = $this->dbh->select_one($sql);

        return $data;
    }

    /**
     * 用户状态置为停用
    **/
    public function changeUserStatus($id,$lockstatus)
    {
        return $this->dbh->update('concap_user',$lockstatus,'sysno=' . intval($id));
    }

    //检测用户是否存在
    public function checkUserLockstatus($username)
    {
        $where = " isdel = 0 AND `username` =  '".$username."'";
        $sql =  "SELECT * FROM concap_user WHERE $where";

        $data = $this->dbh->select_row($sql);

        return $data;
    }

}