<?php 
    require 'vendor/autoload.php';

    $app = new \Slim\Slim();

    $app->view(new \JsonApiView());
    $app->add(new \JsonApiMiddleware());
    // $app->add(new \Slim\Extras\Middleware\JSONPMiddleware());

    use \Michelf\Markdown;

    function getDB($type) {
        $m = new MongoClient();
        $db = $m->selectDB('clg');
        $collection = new MongoCollection($db, $type);

        return $collection;
    }



    function filterContent($content, $parent) {
        if(isset($parent['code']))
        foreach($parent['code'] as $key=>$value) {
            if(!isset($value['type'])) $value['type']="";
            $content = str_replace('[code '.$key.']', '<pre class="'.$value['type'].'">'.$value['text'].'</pre>', $content);
        }
        
        if(isset($parent['images']))
        foreach($parent['images'] as $key=>$value) {
            $content = str_replace('[image '.$key.']', '<img src="'.$value['url'].'">', $content);
        }

        return $content;
    }


    function setParentCode($parent) {
        //CREATE THE ACTION OVERVIEW

        if(isset($parent['type']) && $parent['type'] === "chapter" && isset($parent['children']) && !empty($parent['children'])) {
                foreach($parent['children'] as $child) {
                    
                    if(isset($child['code']) && !empty($child['code'])) {
                        foreach($child['code'] as $code) {
                            $parent['code'][] = $code;
                        }
                    } else {

                    }

                }
        }

        return $parent;
    }

    function setParentMarkdown($parent) {
        //FILTER CONTENT FOR CODE AND IMAGES AND MARKDOWN
        if(isset($parent['content'])) {
            $parent['content'] = filterContent($parent['content'], $parent);
            $parent['content'] = Markdown::defaultTransform($parent['content']);
        }
        
        if(isset($parent['additionalContent'])) {
            foreach($parent['additionalContent'] as $content) {
                $content['text'] = Markdown::defaultTransform($content['text']);
            }
        }
        return $parent;
    }


    //WALK THE TREE
    function walk($parent) {
        
        $parent = setParentCode($parent);

        $parent = setParentMarkdown($parent);
        
        //RECURSIVELY WALK CHILDREN FOR FORMATTING
        if(isset($parent['children'])) {
            $children = array();

            //walk the children and do formatting.
            foreach($parent['children'] as $key=>$child) {
                $child['index'] = $key+1;
                $child['indexOf'] = count($parent['children']);
                $children[] = walk($child);
            }
            $parent['children'] = $children;
        }



        return $parent;
    }

    function nextPrev($children) {
            $i = 0;
            $c = count($children)-1;

            foreach($children as $child) {

                if(isset($child['next']) || isset($child['previous'])) {
                    next($children);
                }

                    if($i < $c) {
                        $next = $children[$i+1];
                        foreach($next as $key=>$value) {
                            if(is_array($value)) {
                                unset($next[$key]);
                            }
                            // unset($next['children']);

                        }
                        $children[$i]['next'] = $next;
                    }

                    if($i > 0) {
                        $previous = $children[$i-1];
                        foreach($previous as $key=>$value) {
                            if(is_array($value)) {
                                unset($previous[$key]);
                            }
                            // unset($previous['children']);
                        }
                        $children[$i]['previous'] = $previous;
                    }

                    //OMG am i really gonna recursively do this again?!
                    if(isset($children[$i]['children'])) {
                        //YUP!
                        $children[$i]['children'] = nextPrev($children[$i]['children']);
                    }

                    
                    $i++;    
            }

            return $children;
    }



    function prepData($parent) {

        //walk the tree and format contet
        $output = walk($parent);

        //if the object has children, then add next and previous
        if($output['children']) {
            $output['children'] = nextPrev($output['children']);
        }

        return $output;

    }










    $app->get('/', function() use ($app) {

        $app->render(200,array(
                'msg' => 'Welcome to my json API!',
            ));
    });

    $app->get('/(:type)', function($type) use ($app) {
        $collection = getDB($type);

        foreach($collection->find() as $item) : 
            $output[] = $item;
        endforeach;

        $app->render(200,array('data' => $output));
    });

    $app->get('/(:type)/overview', function($type) use ($app) {
        $collection = getDB($type);
        
        foreach($collection->find() as $item) : 
            unset($item['children']);
            $output[] = $item;
        endforeach;

        $app->render(200,array('data' => $output));
    });


/************************************
    GUIDES
************************************/

    $app->get('/(:type)/slug/(:slug)', function($type, $slug) use ($app) {
        $collection = getDB($type);
        
        $output = $collection->findOne(array("slug"=>$slug));
      
        $app->render(200,array('data' => $output));


    });

    $app->get('/(:type)/slug/(:slug)/markdown', function($type, $slug) use ($app) {
        $collection = getDB($type);
        
        $output = walk( $collection->findOne(array("slug"=>$slug)) );

        $app->render(200,array('data' => $output));

    });


    $app->get('/(:type)/id/(:id)', function($type, $id) use ($app) {
        $collection = getDB($type);
        
        $output = $collection->findOne(array("_id"=>new MongoId($id)));
      
        $app->render(200,array('data' => $output));
    });


/************************************
    BOOK
************************************/

    $app->get('/(:type)/slug/(:slug)/(:bookSlug)', function($type, $slug, $bookSlug) use ($app) {
        $collection = getDB($type);
        
        $guide = $collection->findOne(array("slug"=>$slug));

        $book;
        foreach($guide['children'] as $child) {
            if($child['slug'] === $bookSlug) {
                $book = $child;
            }
        }

        $output = array(
            'guide'=>$guide,
            'book'=>$book
        );
        $app->render(200,array('data' => $output));


    });



    $app->get('/(:type)/slug/(:slug)/(:bookSlug)/markdown', function($type, $slug, $bookSlug) use ($app) {
        $collection = getDB($type);
        
        $guide = prepData( $collection->findOne(array("slug"=>$slug)) );

        $book;
        foreach($guide['children'] as $child) {
            if($child['slug'] === $bookSlug) {
                $book = $child;
            }
        }

        $output = array(
            'guide'=>$guide,
            'book'=>$book
        );
        $app->render(200,array('data' => $output));


    });


/************************************
    CHAPTER
************************************/

    $app->get('/(:type)/slug/(:slug)/(:bookSlug)/(:chapterSlug)', function($type, $slug, $bookSlug, $chapterSlug) use ($app) {
        $collection = getDB($type);
        
        $guide = $collection->findOne(array("slug"=>$slug));

        $book;
        foreach($guide['children'] as $child) {
            if($child['slug'] === $bookSlug) {
                $book = $child;
            }
        }

        $chapter;
        foreach($book['children'] as $child) {
            if($child['slug'] === $chapterSlug) {
                $chapter = $child;
            }
        }

        $output = array(
            'guide'=>$guide,
            'book'=>$book,
            'chapter'=>$chapter
        );
        $app->render(200,array('data' => $output));


    });

    $app->get('/(:type)/slug/(:slug)/(:bookSlug)/(:chapterSlug)/markdown', function($type, $slug, $bookSlug, $chapterSlug) use ($app) {
        $collection = getDB($type);
        
        $guide = prepData($collection->findOne(array("slug"=>$slug)));

        $book;
        foreach($guide['children'] as $child) {
            if($child['slug'] === $bookSlug) {
                $book = $child;
            }
        }

        $chapter;
        if($book['children'])
        foreach($book['children'] as $child) {
            if(isset($child['slug']) && $child['slug'] === $chapterSlug) {
                $chapter = $child;
            }
        }

        $output = array(
            'guide'=>$guide,
            'book'=>$book,
            'chapter'=>$chapter
        );

        $app->render(200,array('data' => $output));


    });


        $app->run();

?>