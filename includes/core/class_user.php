<?php

class User {

    // GENERAL
    

    public static function user_info_auth($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function user_info($user_id) {
        $q = DB::query("SELECT user_id, plot_id, village_id, first_name, last_name, phone, phone_code, email, last_login, updated
            FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'plot_id' =>$row['plot_id'],
                'village_id' =>(int) $row['village_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'phone_code' => $row['phone_code'],
                'email' => $row['email'],
                'last_login' => $row['last_login']
            ];
        } else {
            return [
                'id' => 0,
                'plot_id' => 0,
                'village_id' => 0,
                'first_name' => '',
                'last_name' => '',
                'phone' => 0,
                'phone_code' => 0,
                'email' => '',
                'last_login' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) {$where[] = "last_name LIKE '%".$search."%'";
            $where[] = "first_name LIKE '%" . $search . "%'";
            $where[] = "email LIKE '%" . $search . "%'";
            $where[] = "phone LIKE '%" . $search . "%'";
        }
        $where = $where ? "WHERE ".implode(" OR ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users ".$where." ORDER BY last_name+0 LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'last_login' => $row['last_login'],
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = []) {
        $info = User::Users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    // ACTIONS

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info($user_id,null));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        if (!empty($d['first_name']) || !empty($d['last_name']) || !empty($d['phone']) || !empty($d['email']) || !empty($d['last_login'])) {
        
            // vars
            $user_id  = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
            $plot_id  = isset($d['plot_id']) && trim($d['plot_id']) ? $d['plot_id'] : '';
            $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
            $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
            $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : '';
            $email = isset($d['email']) && trim($d['email']) ? strtolower(trim($d['email'])) : '';
            $updated = isset($d['updated']) ? preg_replace('~\D+~', '', $d['updated']) : '';
            $last_login	= isset($d['last_login']) ? preg_replace('~\D+~', '', $d['last_login']) : '';
            $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
            // update
    
            if ($user_id) {
                $set = [];
                $set[] = "plot_id='".$plot_id."'";
                $set[] = "first_name='".$first_name."'";
                $set[] = "last_name='".$last_name."'";
                $set[] = "phone='".$phone."'";
                $set[] = "email='".$email."'";
                $set[] = "updated='".Session::$ts."'";
                $set = implode(", ", $set);
                DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
            } else {
                DB::query("INSERT INTO users (
                    plot_id,
                    first_name,
                    last_name,
                    phone,
                    email,
                    updated
                ) VALUES (
                    '".$plot_id."',
                    '".$first_name."',
                    '".$last_name."',
                    '".$phone."',
                    '".$email."',
                    '".Session::$ts."'
                );") or die (DB::error());
            }
            // output
            return User::users_fetch(['offset' => $offset]);
        }
    }

    public static function user_delete($d = []) {
        $user_id = (int) isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        if ($user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user_id'];
        }

        $q = DB::query("DELETE FROM users WHERE user_id= ".$user_id.";") or die (DB::error());

        if ($q) {
            return ['success' => true, 'message' => 'User deleted successfully', 'data' => User::users_fetch(['offset' => $offset])];
        } else {
            return ['success' => false, 'message' => 'Failed to delete user', 'data' => User::users_fetch(['offset' => $offset])];
        }
    }
}
