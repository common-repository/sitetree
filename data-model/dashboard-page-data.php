<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 * ************************************************************ */

if (! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$indent             = '';
$indent_step        = '&nbsp;&nbsp;&nbsp;';
$first_option_title = '&mdash; ' . __( 'Select', 'sitetree' ) . ' &mdash;';
$options            = array( $first_option_title );

$excluded_ids_condition = $query_string = '';
$page_on_front          = (int) get_option( 'page_on_front' );
$page_for_posts         = (int) get_option( 'page_for_posts' );
$meta_key               = $this->db->prepareMetaKey( 'is_ghost_page' );

if ( $page_on_front || $page_for_posts ) {
    $excluded_ids_condition =  'p.ID NOT IN (' . $page_on_front . ',' . $page_for_posts . ') AND';
}

$query_clauses = array(
    'SELECT'          => 'p.ID, p.post_title, p.post_parent',
    'FROM'            => "{$wpdb->posts} AS p",
    'LEFT_OUTER_JOIN' => "{$wpdb->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key = {$meta_key}",
    'WHERE'           => "{$excluded_ids_condition} p.post_type = 'page' AND p.post_status = 'publish' AND 
                          p.post_password = '' AND pm.post_id IS NULL",
    'ORDER_BY'        => 'p.menu_order, p.post_title ASC'
);

/**
 * @since 5.3
 */
$query_clauses = apply_filters( 'sitetree_dashboard_page_data_pages_dropdown_query', $query_clauses );

$ids_flags       = array();
$pages_by_parent = array();
$pages           = $this->db->getResults( $query_clauses );

foreach ( $pages as $page ) {
    $page->ID          = (int) $page->ID;
    $page->post_parent = (int) $page->post_parent;
    $page->post_title  = esc_attr( $page->post_title );
    
    $ids_flags[$page->ID] = true;
}

foreach ( $pages as $page ) {
    if ( ( $page->post_parent === 0 ) || !isset( $ids_flags[$page->post_parent] ) ) {
        $options[$page->ID] = $page->post_title;
    }
    else {
        $pages_by_parent[$page->post_parent][] = $page;
    }
}

while( $pages_by_parent ) {
    $array   = array();
    $indent .= $indent_step;

    foreach( $options as $_page_id => $title ) {
        $array[$_page_id] = $title;
        
        if ( isset( $pages_by_parent[$_page_id] ) ) {
            foreach ( $pages_by_parent[$_page_id] as $page ) {
                $array[ (int) $page->ID ] = $indent . $page->post_title;
            }

            unset( $pages_by_parent[$_page_id] );
        }
    }

    $options = $array;
}

$post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
$taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'objects' );

$fieldset_tooltip = __( 'What content types do you want to include?', 'sitetree' );
$tooltips         = array(
    'pages'      => __( 'Pages', 'sitetree' ),
    'posts'      => __( 'Posts', 'sitetree' ),
    'authors'    => __( "Authors' Pages", 'sitetree' ),
    'categories' => __( 'Categories', 'sitetree' ),
    'tags'       => __( 'Tags', 'sitetree' )
);

$languages = array(
    'aar'   => 'Afar',
    'abk'   => 'Abkhazian',
    'ace'   => 'Achinese',
    'ach'   => 'Acoli',
    'ada'   => 'Adangme',
    'ady'   => 'Adyghe',
    'afh'   => 'Afrihili',
    'afr'   => 'Afrikaans',
    'ain'   => 'Ainu',
    'aka'   => 'Akan',
    'akk'   => 'Akkadian',
    'alb'   => 'Albanian',
    'ale'   => 'Aleut',
    'alt'   => 'Southern Altai',
    'amh'   => 'Amharic',
    'anp'   => 'Angika',
    'ara'   => 'Arabic',
    'arc'   => 'Official Aramaic',
    'arg'   => 'Aragonese',
    'arm'   => 'Armenian',
    'arn'   => 'Mapudungun',
    'arp'   => 'Arapaho',
    'arw'   => 'Arawak',
    'asm'   => 'Assamese',
    'ast'   => 'Asturian',
    'ava'   => 'Avaric',
    'ave'   => 'Avestan',
    'awa'   => 'Awadhi',
    'aym'   => 'Aymara',
    'aze'   => 'Azerbaijani',
    'bak'   => 'Bashkir',
    'bal'   => 'Baluchi',
    'bam'   => 'Bambara',
    'ban'   => 'Balinese',
    'baq'   => 'Basque',
    'bas'   => 'Basa',
    'bej'   => 'Beja',
    'bel'   => 'Belarusian',
    'bem'   => 'Bemba',
    'ben'   => 'Bengali',
    'bho'   => 'Bhojpuri',
    'bik'   => 'Bikol',
    'bin'   => 'Bini',
    'bis'   => 'Bislama',
    'bla'   => 'Siksika',
    'bos'   => 'Bosnian',
    'bra'   => 'Braj',
    'bre'   => 'Breton',
    'bua'   => 'Buriat',
    'bug'   => 'Buginese',
    'bul'   => 'Bulgarian',
    'bur'   => 'Burmese',
    'byn'   => 'Blin',
    'cad'   => 'Caddo',
    'car'   => 'Galibi Carib',
    'cat'   => 'Catalan',
    'ceb'   => 'Cebuano',
    'cze'   => 'Czech',
    'cha'   => 'Chamorro',
    'chb'   => 'Chibcha',
    'che'   => 'Chechen',
    'chg'   => 'Chagatai',
    'zh-tw' => 'Chinese',
    'zh-cn' => 'Chinese, Simplified',
    'chk'   => 'Chuukese',
    'chm'   => 'Mari',
    'chn'   => 'Chinook jargon',
    'cho'   => 'Choctaw',
    'chp'   => 'Chipewyan',
    'chr'   => 'Cherokee',
    'chu'   => 'Church Slavic',
    'chv'   => 'Chuvash',
    'chy'   => 'Cheyenne',
    'cnr'   => 'Montenegrin',
    'cop'   => 'Coptic',
    'cor'   => 'Cornish',
    'cos'   => 'Corsican',
    'cpe'   => 'Creoles and pidgins, English based',
    'cpf'   => 'Creoles and pidgins, French-based',
    'cpp'   => 'Creoles and pidgins, Portuguese-based',
    'cre'   => 'Cree',
    'crh'   => 'Crimean Tatar',
    'crp'   => 'Creoles and pidgins',
    'csb'   => 'Kashubian',
    'wel'   => 'Welsh',
    'cze'   => 'Czech',
    'dak'   => 'Dakota',
    'dan'   => 'Danish',
    'dar'   => 'Dargwa',
    'del'   => 'Delaware',
    'den'   => 'Slave (Athapascan)',
    'ger'   => 'German',
    'dgr'   => 'Dogrib',
    'din'   => 'Dinka',
    'div'   => 'Divehi',
    'doi'   => 'Dogri',
    'dsb'   => 'Lower Sorbian',
    'dua'   => 'Duala',
    'dut'   => 'Dutch',
    'dyu'   => 'Dyula',
    'dzo'   => 'Dzongkha',
    'efi'   => 'Efik',
    'egy'   => 'Egyptian (Ancient)',
    'eka'   => 'Ekajuk',
    'elx'   => 'Elamite',
    'eng'   => 'English',
    'epo'   => 'Esperanto',
    'est'   => 'Estonian',
    'baq'   => 'Basque',
    'ewe'   => 'Ewe',
    'ewo'   => 'Ewondo',
    'fan'   => 'Fang',
    'fao'   => 'Faroese',
    'per'   => 'Persian',
    'fat'   => 'Fanti',
    'fij'   => 'Fijian',
    'fil'   => 'Filipino',
    'fin'   => 'Finnish',
    'fon'   => 'Fon',
    'fre'   => 'French',
    'frr'   => 'Northern Frisian',
    'frs'   => 'Eastern Frisian',
    'fry'   => 'Western Frisian',
    'ful'   => 'Fulah',
    'fur'   => 'Friulian',
    'gaa'   => 'Ga',
    'gay'   => 'Gayo',
    'gba'   => 'Gbaya',
    'geo'   => 'Georgian',
    'ger'   => 'German',
    'gez'   => 'Geez',
    'gil'   => 'Gilbertese',
    'gla'   => 'Gaelic',
    'gle'   => 'Irish',
    'glg'   => 'Galician',
    'glv'   => 'Manx',
    'gon'   => 'Gondi',
    'gor'   => 'Gorontalo',
    'got'   => 'Gothic',
    'grb'   => 'Grebo',
    'gre'   => 'Greek',
    'grn'   => 'Guarani',
    'gsw'   => 'Swiss German',
    'guj'   => 'Gujarati',
    'hai'   => 'Haida',
    'hat'   => 'Haitian',
    'hau'   => 'Hausa',
    'haw'   => 'Hawaiian',
    'heb'   => 'Hebrew',
    'her'   => 'Herero',
    'hil'   => 'Hiligaynon',
    'hin'   => 'Hindi',
    'hit'   => 'Hittite',
    'hmn'   => 'Hmong',
    'hmo'   => 'Hiri Motu',
    'hrv'   => 'Croatian',
    'hsb'   => 'Upper Sorbian',
    'hun'   => 'Hungarian',
    'hup'   => 'Hupa',
    'arm'   => 'Armenian',
    'iba'   => 'Iban',
    'ibo'   => 'Igbo',
    'ice'   => 'Icelandic',
    'ido'   => 'Ido',
    'iii'   => 'Sichuan Yi',
    'iku'   => 'Inuktitut',
    'ilo'   => 'Iloko',
    'ind'   => 'Indonesian',
    'inh'   => 'Ingush',
    'ipk'   => 'Inupiaq',
    'ice'   => 'Icelandic',
    'ita'   => 'Italian',
    'jav'   => 'Javanese',
    'jbo'   => 'Lojban',
    'jpn'   => 'Japanese',
    'jpr'   => 'Judeo-Persian',
    'jrb'   => 'Judeo-Arabic',
    'kaa'   => 'Kara-Kalpak',
    'kab'   => 'Kabyle',
    'kac'   => 'Kachin',
    'kal'   => 'Kalaallisut',
    'kam'   => 'Kamba',
    'kan'   => 'Kannada',
    'kas'   => 'Kashmiri',
    'geo'   => 'Georgian',
    'kau'   => 'Kanuri',
    'kaw'   => 'Kawi',
    'kaz'   => 'Kazakh',
    'kbd'   => 'Kabardian',
    'kha'   => 'Khasi',
    'khm'   => 'Central Khmer',
    'kho'   => 'Khotanese',
    'kik'   => 'Kikuyu',
    'kin'   => 'Kinyarwanda',
    'kir'   => 'Kirghiz',
    'kmb'   => 'Kimbundu',
    'kok'   => 'Konkani',
    'kom'   => 'Komi',
    'kon'   => 'Kongo',
    'kor'   => 'Korean',
    'kos'   => 'Kosraean',
    'kpe'   => 'Kpelle',
    'krc'   => 'Karachay-Balkar',
    'krl'   => 'Karelian',
    'kru'   => 'Kurukh',
    'kua'   => 'Kuanyama',
    'kum'   => 'Kumyk',
    'kur'   => 'Kurdish',
    'kut'   => 'Kutenai',
    'lad'   => 'Ladino',
    'lah'   => 'Lahnda',
    'lam'   => 'Lamba',
    'lao'   => 'Lao',
    'lat'   => 'Latin',
    'lav'   => 'Latvian',
    'lez'   => 'Lezghian',
    'lim'   => 'Limburgan',
    'lin'   => 'Lingala',
    'lit'   => 'Lithuanian',
    'lol'   => 'Mongo',
    'loz'   => 'Lozi',
    'ltz'   => 'Luxembourgish',
    'lua'   => 'Luba-Lulua',
    'lub'   => 'Luba-Katanga',
    'lug'   => 'Ganda',
    'lui'   => 'Luiseno',
    'lun'   => 'Lunda',
    'luo'   => 'Luo (Kenya and Tanzania)',
    'lus'   => 'Lushai',
    'mac'   => 'Macedonian',
    'mad'   => 'Madurese',
    'mag'   => 'Magahi',
    'mah'   => 'Marshallese',
    'mai'   => 'Maithili',
    'mak'   => 'Makasar',
    'mal'   => 'Malayalam',
    'man'   => 'Mandingo',
    'mao'   => 'Maori',
    'mar'   => 'Marathi',
    'mas'   => 'Masai',
    'may'   => 'Malay',
    'mdf'   => 'Moksha',
    'mdr'   => 'Mandar',
    'men'   => 'Mende',
    'min'   => 'Minangkabau',
    'mac'   => 'Macedonian',
    'mlg'   => 'Malagasy',
    'mlt'   => 'Maltese',
    'mnc'   => 'Manchu',
    'mni'   => 'Manipuri',
    'moh'   => 'Mohawk',
    'mon'   => 'Mongolian',
    'mos'   => 'Mossi',
    'mao'   => 'Maori',
    'may'   => 'Malay',
    'mus'   => 'Creek',
    'mwl'   => 'Mirandese',
    'mwr'   => 'Marwari',
    'bur'   => 'Burmese',
    'myv'   => 'Erzya',
    'nap'   => 'Neapolitan',
    'nau'   => 'Nauru',
    'nav'   => 'Navajo',
    'nbl'   => 'Ndebele, South',
    'nde'   => 'Ndebele, North',
    'ndo'   => 'Ndonga',
    'nep'   => 'Nepali',
    'new'   => 'Nepal Bhasa',
    'nia'   => 'Nias',
    'niu'   => 'Niuean',
    'dut'   => 'Dutch',
    'nno'   => 'Norwegian Nynorsk',
    'nob'   => 'Bokmål, Norwegian',
    'nog'   => 'Nogai',
    'non'   => 'Norse, Old',
    'nor'   => 'Norwegian',
    'nso'   => 'Pedi',
    'nwc'   => 'Classical Newari',
    'nya'   => 'Chichewa',
    'nym'   => 'Nyamwezi',
    'nyn'   => 'Nyankole',
    'nyo'   => 'Nyoro',
    'nzi'   => 'Nzima',
    'oji'   => 'Ojibwa',
    'ori'   => 'Oriya',
    'orm'   => 'Oromo',
    'osa'   => 'Osage',
    'oss'   => 'Ossetian',
    'pag'   => 'Pangasinan',
    'pal'   => 'Pahlavi',
    'pam'   => 'Pampanga',
    'pan'   => 'Panjabi',
    'pap'   => 'Papiamento',
    'pau'   => 'Palauan',
    'per'   => 'Persian',
    'phn'   => 'Phoenician',
    'pli'   => 'Pali',
    'pol'   => 'Polish',
    'pon'   => 'Pohnpeian',
    'por'   => 'Portuguese',
    'pus'   => 'Pushto',
    'que'   => 'Quechua',
    'raj'   => 'Rajasthani',
    'rap'   => 'Rapanui',
    'rar'   => 'Rarotongan',
    'roh'   => 'Romansh',
    'rom'   => 'Romany',
    'rum'   => 'Romanian',
    'rum'   => 'Romanian',
    'run'   => 'Rundi',
    'rup'   => 'Aromanian',
    'rus'   => 'Russian',
    'sad'   => 'Sandawe',
    'sag'   => 'Sango',
    'sah'   => 'Yakut',
    'sam'   => 'Samaritan Aramaic',
    'san'   => 'Sanskrit',
    'sas'   => 'Sasak',
    'sat'   => 'Santali',
    'scn'   => 'Sicilian',
    'sco'   => 'Scots',
    'sel'   => 'Selkup',
    'shn'   => 'Shan',
    'sid'   => 'Sidamo',
    'sin'   => 'Sinhala',
    'slo'   => 'Slovak',
    'slo'   => 'Slovak',
    'slv'   => 'Slovenian',
    'sma'   => 'Southern Sami',
    'sme'   => 'Northern Sami',
    'smj'   => 'Lule Sami',
    'smn'   => 'Inari Sami',
    'smo'   => 'Samoan',
    'sms'   => 'Skolt Sami',
    'sna'   => 'Shona',
    'snd'   => 'Sindhi',
    'snk'   => 'Soninke',
    'sog'   => 'Sogdian',
    'som'   => 'Somali',
    'sot'   => 'Sotho, Southern',
    'spa'   => 'Spanish',
    'alb'   => 'Albanian',
    'srd'   => 'Sardinian',
    'srn'   => 'Sranan Tongo',
    'srp'   => 'Serbian',
    'srr'   => 'Serer',
    'ssw'   => 'Swati',
    'suk'   => 'Sukuma',
    'sun'   => 'Sundanese',
    'sus'   => 'Susu',
    'sux'   => 'Sumerian',
    'swa'   => 'Swahili',
    'swe'   => 'Swedish',
    'syr'   => 'Syriac',
    'tah'   => 'Tahitian',
    'tam'   => 'Tamil',
    'tat'   => 'Tatar',
    'tel'   => 'Telugu',
    'tem'   => 'Timne',
    'ter'   => 'Tereno',
    'tet'   => 'Tetum',
    'tgk'   => 'Tajik',
    'tgl'   => 'Tagalog',
    'tha'   => 'Thai',
    'tib'   => 'Tibetan',
    'tig'   => 'Tigre',
    'tir'   => 'Tigrinya',
    'tiv'   => 'Tiv',
    'tkl'   => 'Tokelau',
    'tlh'   => 'Klingon',
    'tli'   => 'Tlingit',
    'tmh'   => 'Tamashek',
    'tog'   => 'Tonga (Nyasa)',
    'ton'   => 'Tonga (Tonga Islands)',
    'tpi'   => 'Tok Pisin',
    'tsi'   => 'Tsimshian',
    'tsn'   => 'Tswana',
    'tso'   => 'Tsonga',
    'tuk'   => 'Turkmen',
    'tum'   => 'Tumbuka',
    'tur'   => 'Turkish',
    'tvl'   => 'Tuvalu',
    'twi'   => 'Twi',
    'tyv'   => 'Tuvinian',
    'udm'   => 'Udmurt',
    'uga'   => 'Ugaritic',
    'uig'   => 'Uighur',
    'ukr'   => 'Ukrainian',
    'umb'   => 'Umbundu',
    'und'   => 'Undetermined',
    'urd'   => 'Urdu',
    'uzb'   => 'Uzbek',
    'vai'   => 'Vai',
    'ven'   => 'Venda',
    'vie'   => 'Vietnamese',
    'vol'   => 'Volapük',
    'vot'   => 'Votic',
    'wal'   => 'Wolaitta',
    'war'   => 'Waray',
    'was'   => 'Washo',
    'wel'   => 'Welsh',
    'wln'   => 'Walloon',
    'wol'   => 'Wolof',
    'xal'   => 'Kalmyk',
    'xho'   => 'Xhosa',
    'yao'   => 'Yao',
    'yap'   => 'Yapese',
    'yid'   => 'Yiddish',
    'yor'   => 'Yoruba',
    'zap'   => 'Zapotec',
    'zen'   => 'Zenaga',
    'zha'   => 'Zhuang',
    'zul'   => 'Zulu',
    'zun'   => 'Zuni',
    'zza'   => 'Zaza'
);

/* ************************************************************ */

$site_tree_section = new Section( 'Site Tree', 'site_tree' );
$site_tree_section->addField(
    new Field( 'page_for_site_tree', 'Dropdown', 'choice', 
                       __( 'In which page do you want to show your Site Tree?', 'sitetree' ), '', 0, $options )
);

$site_tree_fieldset = new Fieldset( $fieldset_tooltip, 'site_tree_content_types', 'sortable' );
$site_tree_fieldset->addField( new Field( 'page', 'MetaCheckbox', 'bool', '', $tooltips['pages'], true ) );
$site_tree_fieldset->addField( new Field( 'post', 'MetaCheckbox', 'bool', '', $tooltips['posts'], true ) );

foreach ( $post_types as $post_type ) {
    $site_tree_fieldset->addField( new Field( $post_type->name, 'MetaCheckbox', 'bool', '', $post_type->label ) );
}

$site_tree_fieldset->addField(
    new Field( 'authors', 'MetaCheckbox', 'bool', '', $tooltips['authors'] )
);
$site_tree_fieldset->addField( new Field( 'category', 'MetaCheckbox', 'bool', '', $tooltips['categories'] ) );
$site_tree_fieldset->addField( new Field( 'post_tag', 'MetaCheckbox', 'bool', '', $tooltips['tags'] ) );

foreach ( $taxonomies as $taxonomy ) {
    $site_tree_fieldset->addField( new Field( $taxonomy->name, 'MetaCheckbox', 'bool', '', $taxonomy->label ) );
}

$site_tree_content_types = $this->db->getOption( 'site_tree_content_types' );

if ( $site_tree_content_types ) {
    $site_tree_fieldset->reorderFields( $site_tree_content_types );
}

$site_tree_section->addField( $site_tree_fieldset );
$site_tree_section->addField( new Field( 'pagination_threshold', 'NumberField', 'positive_number', 
                                         __( 'After how many items should I start paginating?', 'sitetree'), 
                                         '', 100, array( 'min_value' => 10, 'max_value' => 500 ) ) );

$this->registerSection( $site_tree_section );

/* ************************************************************ */

$sitemap_section = new Section( __( 'Google Sitemaps', 'sitetree' ), 'sitemap' );
$sitemap_section->addField( new Field( 'sitemap_filename', 'TextField', 'key', 
                                       __( 'Filename (without extension):', 'sitetree' ), '', 'sitemap' ) );

$sitemap_fieldset = new Fieldset( $fieldset_tooltip, 'sitemap_content_types' );
$sitemap_fieldset->addField( new Field( 'page', 'Checkbox', 'bool', '', $tooltips['pages'], true ) );
$sitemap_fieldset->addField( new Field( 'post', 'Checkbox', 'bool', '', $tooltips['posts'], true ) );

foreach ( $post_types as $post_type ) {
    $sitemap_fieldset->addField( new Field( $post_type->name, 'Checkbox', 'bool', '', $post_type->label ) );
}

$sitemap_fieldset->addField(
    new Field( 'authors', 'Checkbox', 'bool', '', $tooltips['authors'] )
);
$sitemap_fieldset->addField( new Field( 'category', 'Checkbox', 'bool', '', $tooltips['categories'] ) );
$sitemap_fieldset->addField( new Field( 'post_tag', 'Checkbox', 'bool', '', $tooltips['tags'] ) );

foreach ( $taxonomies as $taxonomy ) {
    $sitemap_fieldset->addField( new Field( $taxonomy->name, 'Checkbox', 'bool', '', $taxonomy->label ) );
}

$sitemap_section->addField( $sitemap_fieldset );
$sitemap_section->addField( new Field( 'max_permalinks_in_sitemap', 'NumberField', 'positive_number', 
                                       __( 'How many permalinks should each sitemap contain?', 'sitetree'), 
                                       '', 1000, array( 'min_value' => 500, 'max_value' => 10000 ) ) );

$this->registerSection( $sitemap_section );

/* ************************************************************ */

$newsmap_section  = new Section( __( 'Google News Sitemaps', 'sitetree' ), 'newsmap' );

$newsmap_fieldset = new Fieldset( $fieldset_tooltip, 'newsmap_content_types' );
$newsmap_fieldset->addField( new Field( 'post', 'Checkbox', 'bool', '', $tooltips['posts'], true ) );

foreach ( $post_types as $post_type ) {
    $newsmap_fieldset->addField( new Field( $post_type->name, 'Checkbox', 'bool', '', $post_type->label ) );
}

$newsmap_section->addField(
    new Field( 'publication_name', 'TextField', 'plain_text', 
                       __( 'Publisher:', 'sitetree' ), '', get_bloginfo( 'name' ) )
);
$newsmap_section->addField(
    new Field( 'publication_lang', 'Dropdown', 'choice', 
                       __( 'Publication language:', 'sitetree' ), '', 'eng', $languages )
);
$newsmap_section->addField( $newsmap_fieldset );
$newsmap_section->addField( new Field( 'max_permalinks_in_newsmap', 'NumberField', 'positive_number', 
                                       __( 'How many news should each sitemap contain?', 'sitetree'), 
                                       '', 1000, array( 'min_value' => 100, 'max_value' => 1000 ) ) );

$this->registerSection( $newsmap_section );
?>