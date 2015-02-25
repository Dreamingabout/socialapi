<?php
class DataSaver{

    /**
     * @var PHPExcel
     * Переменная, хранящая объект PhpExcel
     */
    protected $_phpExcel;

    /**
     * Инициализируем объекты типа PHPExcel
     */
    public function __construct()
    {
        $this->_phpExcel = new PHPExcel();

    }

    /**
     * @param $phpExcel
     * @param string $startCol
     * @param null $lastCol
     * Метод для автоматической установки ширины столбца Excel равного ширине его содержимого
     */
    public function autoFitColumnWidthToContent($phpExcel, $startCol = 'A', $lastCol = null)
    {
        $activeSheet = $phpExcel->getActiveSheet();
        if ($lastCol == null) {
            $lastColumn = $activeSheet->getColumnDimension($activeSheet->getHighestColumn())->getColumnIndex();
        }
        for ($col = $startCol; $col !== $lastColumn; $col++) {
            $activeSheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * @param $sheetNumber
     * @return PHPExcel_Worksheet
     * Создает новый лист excel
     */
    public function sheetCreator($collectedDetailData)
    {
        $sheetCounter = count($collectedDetailData);
        for ($i = 0; $i < $sheetCounter; $i++) {
            $this->_phpExcel->createSheet($i);
        }
    }

    /**
     * @param array $headerCells
     * @throws PHPExcel_Exception
     * Создает "шапку" страницы общей информации
     */
    public function fileInitHeaderTolal(array $headerCells = array(
            'groupId' => 'Идентификатор группы',
            'groupShortName' => 'Короткое имя',
            'groupsSite'=>'Сайт',
            'groupsType'=>'Тип',
            'groupsActivity'=>'Сфера деятельности',
            'groupMembersCount' => 'Общее количество пользователей',
            'groupLikesTotal' => 'Лайков всего',
            'groupRepostsTotal' => 'Репостов всего',
            'groupCommentsTotal' => 'Комментариев всего',
            'groupsCommentsByOwnerTotal' => 'Комментариев владельца всего',
            'groupPostsCount' => 'Количество постов',
            'groupUserPostsCount' => 'Количество постов пользователей',
            'groupUniqueUsers' => 'Уникальных пользователей'
        ))
    {
        $this->_phpExcel->setActiveSheetIndex(0)->setTitle('Сводная');
        $this->_phpExcel->getActiveSheet()->fromArray($headerCells, null, 'A1');
        $this->_phpExcel->getActiveSheet()->getStyle("A1:O1")->getFont()->setBold(true);
    }

    /**
     * @param $collectDetailData
     * @param array $headerCells
     * @throws PHPExcel_Exception
     * Создает шапку для страниц информации по постам
     */
    public function fileInitHeaderPosts($collectDetailData, array $headerCells = array(
            'postId' => 'Идентификатор поста',
            'postComments' => 'Количество комментариев',
            'postLikes' => 'Количество лайков',
            'postReposts' => 'Количество репостов',
            'postIsOwner' => 'Пост владельца',
            'postOwnerId' => 'Идентификатор автора поста',
            'postHashtag' => 'Хэштег поста',
            'postAttachPhoto' => 'Прикрепленное фото',
            'postAttachVideo' => 'Прикрепленное видео',
            'postAttachAudio' => 'Прикрепленное аудио',
            'postAttachDoc' => 'Прикрепленный документ',
            'postAttachGraffiti' => 'Прикепленное граффити',
            'postAttachLink' => 'Прикрепленная ссылка',
            'postAttachNote' => 'Прикрепленная заметка',
            'postAttachPage' => 'Прикрепленная страница',
            'postAttachPoll' => 'Прикрепленное голосование',
            'postAttachAlbum' => 'Прикрепленный альбом',
            'postTimestamp' => 'Unix время',
            'postDate' => 'Дата и время поста',
            'postDateAndTime' => 'Дата поста'
        )){
        $headerPostsCount = count($collectDetailData) + 1;
        for ($i = 1; $i < $headerPostsCount; $i++) {
            $this->_phpExcel->setActiveSheetIndex($i);
            $this->_phpExcel->getActiveSheet()->fromArray($headerCells, null, 'A1');
            $this->_phpExcel->getActiveSheet()->getStyle("A1:T1")->getFont()->setBold(true);


        }
    }


    /**
     * @param $collectedData
     * @param string $title
     * @param string $excel
     * @param string $format
     * @throws PHPExcel_Exception
     * @throws PHPExcel_Reader_Exception
     * Вызывает необходимые для инициализации методы и сохраняет переданный массив данных
     * в корректный xlsx файл
     */
    public function saveData( $groupData, $postData,$names,$toDate,$fromDate, $title = 'Some title', $excel = 'Excel2007', $format = '.xlsx')
    {

        $this->sheetCreator($postData);
        $this->fileInitHeaderTolal();
        $this->fileInitHeaderPosts($postData);
        $rowCount = 2;
        $sheetCount = 1;
        foreach ($groupData as $data) {
            $this->_phpExcel->setActiveSheetIndex(0);
            $this->_phpExcel->getActiveSheet()->fromArray($data, null, 'A' . $rowCount, true);
            ++$rowCount;
            $this->autoFitColumnWidthToContent($this->_phpExcel);


        }

        foreach ($postData as $data) {
            $this->_phpExcel->setActiveSheetIndex($sheetCount);
            $rowCount = 2;
            ++$sheetCount;
            foreach ($data as $post) {

                $this->_phpExcel->getActiveSheet()->setTitle(current($names))->fromArray($post, null, 'A' . $rowCount, true);
                ++$rowCount;
                $this->autoFitColumnWidthToContent($this->_phpExcel);
            }
            next($names);
        }
        $this->_phpExcel->setActiveSheetIndex(0);

        // Указываем первый лист как активный
        $objWriter = PHPExcel_IOFactory::createWriter($this->_phpExcel, $excel);
        $lastWeek = strtotime('last week');
        $thisWeek = strtotime('this week');
        $filename = $toDate.'_'.$fromDate.$format;
        $objWriter->save(dirname(__FILE__)."/reports/xls/".$filename);
    }

}