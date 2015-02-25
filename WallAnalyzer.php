<?php

class WallAnalyzer
{

    const POSTS_CHUNK_SIZE = 100;
    const LIKES_CHUNK_SIZE = 1000;
    const TOKEN = 'dwad32432423e3dwd3wr3r342432423d3rf';
    const MAILTO1 = 'b@mail.ru';
    const MAILTO2 = 'a@mail.ru';

    /**
     * @var resource
     * Переменная для хранения curl_init()
     */
    private $_ch;
    /**
     * @var
     * Переменная для хранения массива постов
     */
    private $_posts;
    /**
     * @var
     * Переменная для хранения массива комментариев
     */
    private $_comments;
    /**
     * @var
     * Переменная для хранения массива лайков
     */
    private $_likes;
    /**
     * @var
     * Переменная для хранения массива репостов
     */
    private $_reposts;
    /**
     * @var
     * Переменная для хранения даты начала выборки
     */
    private $_fromDate;
    /**
     * @var
     * Переменная для хранения даты окончания выборки
     */
    private $_toDate;
    /**
     * @var
     * Переменная для хранения общего количества комментариев
     */
    private $_commentsTotal;
    /**
     * @var
     * Переменная для хранения комментариев владельца группы
     */
    private $_commentsOwnerTotal;
    /**
     * @var
     * Общее количество лайков
     */
    private $_likesTotal;
    /**
     * @var
     * Общее количество репостов
     */
    private $_repostsTotal;
    /**
     * @var int
     * Общее количество постов, оставленных на стене пользователями
     */
    private $_userPostsTotal = 0;
    /**
     * @var array
     * Массив всех пользователей, проявивших активность в сообществе(лайки, репосты, комментарии)
     */
    private $_allActiveUsers = array();
    /**
     * @var
     * Количество уникальных пользователей, проявивших активность в сообществе
     */
    private $_uniqueUsers;
    /**
     * @var
     * Массив общей информации о группах
     */
    private $_groupInfo;
    /**
     * @var
     * Переменная, содержащая объект типа DataSaver, устанавливается в конструкторе
     */
    private $_dataSave;

    /**
     * @var array
     * Массив коротких текстовых идентификаторов групп, по которым необходимо произвести выборку
     */
    public $_names = array('obiru','castorama','leroy_merlin','ikea','club220_volt','official_frutonyanya','temaplay','semper.cafe','agulife');

    /**
     *Конструктор текущего класса
     */
    public function  __construct()
    {
        $this->_ch = curl_init();
        $this->_dataSave = new DataSaver();
    }

    /**
     * @param $hashtag
     * @return array
     * Метод, анализирующий хэштеги
     */
    public function hashTagAnalyzer($hashtag)
    {
        $hashtag .= " ";

        if (preg_match_all('/#([a-z0-9а-я_]+)[\s\@\.!,]/iu', $hashtag, $matches)) {
            return $matches[1];
        } else {
            return array();
        }
    }

    /**
     * @param $attachment
     * @return array
     * Анализирует аттачи
     */
    public function attachmentAnalyzer($attachment)
    {
        $stat = array("photo" => 0,
            "video" => 0,
            "audio" => 0,
            "doc" => 0,
            "graffiti" => 0,
            "link" => 0,
            "note" => 0,
            "page" => 0,
            "poll" => 0,
            "album" => 0,
        );
        if (empty($attachment)) {
            return $stat;
        }
        foreach ($attachment as $attach) {
            $type = $attach->type;
            if ($type == "posted_photo") $type = "photo";
            if ($type == "app") $type = "photo";
            if ($type == "photos_list") die("PHOTOS_LIST behavior unknown");
            $stat[$type] += 1;
        }
        return $stat;
    }


    /**
     * @param $url
     * @return mixed
     * Осуществляет запрос к указанному url при помощи curl
     */
    public function request($url)
    {
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($this->_ch, CURLOPT_POST, 0);
        curl_setopt($this->_ch, CURLOPT_HEADER, 0);
        curl_setopt($this->_ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($this->_ch);
        return json_decode($resp);
    }

    /**
     * @param $method
     * @param array $params
     * @return mixed
     * Метод, обеспечивающий интерфейс для доступа к api vkontakte
     */
    public function api($method, array $params)
    {
        $url = 'https://api.vk.com/method/' . $method . '?v=5.3';
        foreach ($params as $k => $v) {
            if (!is_array($v)) $url .= "&$k=$v";
        }
        $data = $this->request($url);
        return $data;
    }

    /**
     * @param $gid
     * @param $toDate
     * @param int $fromDate
     * Метод, возвращающий массив постов для группы из выборки
     */
    public function getPosts($gid, $toDate, $fromDate = 0)
    {
        $reachedFin = false;
        $postCounter = 0;
        $totalPosts = 0;
        while (!$reachedFin) {
            $obj = $this->api('wall.get', array('owner_id' => '-' . $gid,
                'count' => self::POSTS_CHUNK_SIZE,
                'offset' => $postCounter));
            if(isset($obj->response)){
                $totalPosts = $obj->response->count;
                $postsChunk = $obj->response->items;
                if(is_array($postsChunk)){
                    foreach ($postsChunk as $postElem) {
                        if ((array_key_exists('is_pinned', $postElem)) || ($postElem->date > $fromDate)) {
                            continue;
                        }
                        $postCounter++;
                        if ($postElem->date < $toDate) {
                            $reachedFin = true;
                        } else {
                            if ($postElem->date <= $fromDate) {
                                $ownerPost = false;
                                if ($postElem->from_id == '-' . $gid) {
                                    $ownerPost = true;
                                }
                                $hashtags = $this->hashTagAnalyzer($postElem->text);
                                $tag = implode(',', $hashtags);
                                $post["id"] = $postElem->id;
                                $post["comments"] = $postElem->comments->count;
                                $post["likes"] = $postElem->likes->count;
                                $post["reposts"] = $postElem->reposts->count;
                                $post["is_owner"] = $ownerPost;
                                $post['owner_id'] = $postElem->from_id;
                                $post["hashtags"] = $tag;
                                if (array_key_exists('attachments', $postElem)) {
                                    $attachments = $this->attachmentAnalyzer($postElem->attachments);
                                    $post['attachPhoto'] = $attachments['photo'];
                                    $post['attachVideo'] = $attachments['video'];
                                    $post['attachAudio'] = $attachments['audio'];
                                    $post['attachDoc'] = $attachments['doc'];
                                    $post['attachGraffiti'] = $attachments['graffiti'];
                                    $post['attachLink'] = $attachments['link'];
                                    $post['attachNote'] = $attachments['note'];
                                    $post['attachPage'] = $attachments['page'];
                                    $post['attachPoll'] = $attachments['poll'];
                                    $post['attachAlbum'] = $attachments['album'];
                                } else {
                                    $post['attachPhoto'] = '0';
                                    $post['attachVideo'] = '0';
                                    $post['attachAudio'] = '0';
                                    $post['attachDoc'] = '0';
                                    $post['attachGraffiti'] = '0';
                                    $post['attachLink'] = '0';
                                    $post['attachNote'] = '0';
                                    $post['attachPage'] = '0';
                                    $post['attachPoll'] = '0';
                                    $post['attachAlbum'] = '0';
                                }
                                $post["timestamp"] = $postElem->date;
                                $post["datetime"] = strftime("%d.%m.%Y %H:%M:%S", $postElem->date);
                                $post['date'] = strftime("%d.%m.%Y", $postElem->date);
                                $this->_posts[$gid][$postElem->id] = $post;
                                print_r( $this->_posts[$gid][$postElem->id]);
                            }
                        }
                    }
                }

            }

            if ($postCounter == $totalPosts) {
                $reachedFin = true;
            }
        }

    }

    /**
     * @param $gid
     * @param $pid
     * @param $toDate
     * @param int $fromDate
     * @return array
     * Метод, возвращающий массив комментарием для определенного поста группы из выборки
     */
    public function getComments($gid, $pid, $toDate, $fromDate = 0)
    {
        $this->_comments = array();
        $reachedFin = false;
        $commentCounter = 0;
        $totalComments = 0;
        while (!$reachedFin) {
            $obj = $this->api('wall.getComments', array('owner_id' => '-' . $gid,
                'post_id' => $pid,
                'sort' => 'desc',
                'count' => self::POSTS_CHUNK_SIZE,
                'offset' => $commentCounter));

            if (isset($obj->response)) {
                $totalComments = $obj->response->count;
                $commentsChunk = $obj->response->items;

                if (is_array($commentsChunk)) {
                    foreach ($commentsChunk as $commentElem) {
                        $commentCounter++;
                        if ($commentElem->date < $toDate) {
                            $reachedFin = true;
                        } else {
                            if ($commentElem->date <= $fromDate) {
                                $comment = array('uid' => $commentElem->from_id,
                                    'date' => $commentElem->date,
                                    'dateReadable' => strftime("%d.%m.%Y %H:%M:%S", $commentElem->date));
                                $this->_comments[$commentElem->id] = $comment;
                            }
                        }
                    }
                }

            }
            if ($commentCounter == $totalComments) {
                $reachedFin = true;
            }


        }
        print_r($this->_comments);
        return $this->_comments;
    }

    /**
     * @param $gid
     * @param $pid
     * @return array
     * Метод, возвращающий массив лайков для конкретного поста группы из выборки
     */
    public function getLikes($gid, $pid)
    {
        $this->_likes = array();
        $reachedFin = false;
        $likesCounter = 0;
        while (!$reachedFin) {
            $obj = $this->api('likes.getList', array(
                'type' => 'post',
                'owner_id' => '-' . $gid,
                'item_id' => $pid,
                'count' => self::LIKES_CHUNK_SIZE,
                'offset' => $likesCounter), true, true);

            $totalLikes = $obj->response->count;
            $likesChunk = $obj->response->items;

            foreach ($likesChunk as $likeElem) {
                $likesCounter++;
                $this->_likes[] = $likeElem;
            }
            if (($likesCounter == $totalLikes) || ($likesCounter < self::LIKES_CHUNK_SIZE)) {
                $reachedFin = true;
            }
        }
        echo "likes\n";
        print_r($this->_likes);
        return $this->_likes;
    }

    /**
     * @param $gid
     * @param $pid
     * @return array
     * Метод, возвращающий массив репостов для конкретного поста группы из выборки
     */
    public function getReposts($gid, $pid)
    {

        $this->_reposts = array();
        $reachedFin = false;
        $repostCounter = 0;
        while (!$reachedFin) {
            $obj = $this->api('likes.getList', array(
                'type' => 'post',
                'owner_id' => '-' . $gid,
                'filter' => 'copies',
                'item_id' => $pid,
                'count' => self::LIKES_CHUNK_SIZE,
                'offset' => $repostCounter), true, true);

            $totalLikes = $obj->response->count;
            $likesChunk = $obj->response->items;

            foreach ($likesChunk as $likeElem) {
                $repostCounter++;
                $this->_reposts[] = $likeElem;
            }
            if (($repostCounter == $totalLikes) || ($repostCounter < self::LIKES_CHUNK_SIZE)) {
                $reachedFin = true;
            }
        }
        echo "repo\n";
        print_r($this->_reposts);
        return $this->_reposts;
    }

    /**
     * @return mixed
     * Метод, осуществляющий объединение любого количества переданных массивов, устанавливая ключи нового массива с 0
     */
    public function array_concat()
    {
        $args = func_get_args();
        foreach ($args as $ak => $av) {
            $args[$ak] = array_values($av);
        }
        return call_user_func_array('array_merge', $args);
    }

    /**
     * @param $toDate
     * @param $fromDate
     * @return $this
     * Метод, предназначенный для сборки данных группы
     */
    public function collectData($toDate, $fromDate)
    {
        $this->_uniqueUsers = array_unique($this->_allActiveUsers);
        $this->_toDate = strtotime($toDate);
        $this->_fromDate = strtotime($fromDate);
        foreach ($this->_names as $screenName) {
            $object = $this->api('utils.resolveScreenName', array('screen_name' => $screenName));
            $gid = $object->response->object_id;
            $this->getPosts($gid, $this->_toDate, $this->_fromDate);
            $this->_allActiveUsers = array();
            if(is_array($this->_posts)){
                foreach ($this->_posts as $postId) {
                    $this->_allActiveUsers = array();
                    foreach ($postId as $post_stats) {
                        if (!$post_stats['is_owner']) {
                            $this->_userPostsTotal++;
                        }
                        $this->_commentsTotal += $post_stats['comments'];
                        $this->_likesTotal += $post_stats['likes'];
                        $this->_repostsTotal += $post_stats['reposts'];
                        $this->getLikes($gid, $post_stats['id']);
                        $this->getReposts($gid, $post_stats['id']);
                        $this->_allActiveUsers[] = $post_stats['owner_id'];
                        $comments = $this->getComments($gid, $post_stats['id'], $this->_toDate, $this->_fromDate);
                        if (is_array($comments)) {
                            foreach ($comments as $cid => $comment) {
                                $this->_allActiveUsers[] = $comment['uid'];
                                if ($comment['uid'] == '-' . $gid) {
                                    $this->_commentsOwnerTotal++;
                                }
                            }
                        }
                        $this->_allActiveUsers = $this->array_concat($this->_allActiveUsers, $this->_likes, $this->_reposts);
                        $uniqueUsers = count(array_unique($this->_allActiveUsers));
                        $groups = $this->api('groups.getById', array('group_id' => $screenName, 'fields' => 'members_count,site,activity'));

                        if (isset ($this->_posts[$gid])) {
                            $this->_groupInfo[$gid] = array(
                                'Идентификатор' => $gid,
                                'Короткое имя' => $screenName,
                                'Сайт' => $groups->response[0]->site,
                                'Тип' => $groups->response[0]->type,
                                'Cфера деятельности' => $groups->response[0]->activity,
                                'Количество пользователей' => $groups->response[0]->members_count,
                                'Количество лайков' => $this->_likesTotal,
                                'Количество репостов' => $this->_repostsTotal,
                                'Общее количество комментариев' => $this->_commentsTotal,
                                'Комментарии владельца' => $this->_commentsOwnerTotal,
                                'Общее количество постов' => count($this->_posts[$gid]),
                                'Количество постов пользователей' => $this->_userPostsTotal,
                                'Количество уникальных пользователей' => $uniqueUsers
                            );

                        }

                    }

                    $this->_commentsTotal = 0;
                    $this->_likesTotal = 0;
                    $this->_repostsTotal = 0;
                    $this->_commentsOwnerTotal = 0;
                    $this->_userPostsTotal = 0;
                }
            }

        }
        print_r($this->_groupInfo);


        return $this;

    }

    /**
     * @return $this
     * Метод, делигирующий процесс сохранения методу saveData класса DataSaver
     */
    public function saveData()
    {
    	$toDate = date('m-d', $this->_toDate);
    	$fromDate = date('m-d', $this->_fromDate);
        $this->_dataSave->saveData($this->_groupInfo, $this->_posts, $this->_names,$toDate,$fromDate);
        return $this;
    }

    /**
     *Метод, создающий рассылку сгенерированного отчета на указанные адреса за определенный период
     */
    public function reportSender()
    {
        $email = new PHPMailer();
        $email->IsSMTP();
        $email->Host = "email-smtp.eu-west-1.amazonaws.com";
        $email->SMTPSecure = "tls";
        $email->SMTPDebug = 2;
        $email->SMTPAuth = true;
        $email->Port = 587;
        $email->Username = "aaa";
        $email->Password = "aaa";
        $email->SetFrom('donotreply@lol.ru','donotreply');
        $email->Subject = 'Vk reports';
        $email->Body = "Здравствуйте. В прикрепленном файле отчет по группам вконтакте. Пожалуйста, не отвечайте на это письмо, так как оно отправляется автоматически.";
        $email->AddAddress(self::MAILTO1);
        $email->AddAddress(self::MAILTO2);
        $file_to_attach = dirname(__FILE__) . '/reports/xls/'.date('m-d',$this->_toDate).'_'.date('m-d',$this->_fromDate).'.xlsx';
        $email->AddAttachment($file_to_attach);
        if($email->Send()) echo 'Сообщение отправлено';
        else echo 'Ошибка отправки';



    }


}

