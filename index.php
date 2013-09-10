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



    //WALK THE TREE
    function walk($parent) {
        //CREATE THE ACTION OVERVIEW
        if(isset($parent['type']) && $parent['type'] === "chapter") {
            if(isset($parent['children'])) {
                foreach($parent['children'] as $child) {
                    if(isset($child['code'])) {
                        foreach($child['code'] as $code) {
                            $parent['code'][] = $code;
                        }
                    }
                }
            }
        }

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


        if(isset($parent['children'])) {
            $children = array();

            foreach($parent['children'] as $child) {
                $children[] = walk($child);
            }

            $parent['children'] = $children;
        }


        return $parent;
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

        $app->render(200,$output);
    });

    $app->get('/(:type)/overview', function($type) use ($app) {
        $collection = getDB($type);
        
        foreach($collection->find() as $item) : 
            unset($item['children']);
            $output[] = $item;
        endforeach;

        $app->render(200,$output);
    });

    $app->get('/(:type)/slug/(:slug)', function($type, $slug) use ($app) {
        $collection = getDB($type);
        
        $output = $collection->findOne(array("slug"=>$slug));
      
        $app->render(200,$output);

    });

    $app->get('/(:type)/slug/(:slug)/markdown', function($type, $slug) use ($app) {
        $collection = getDB($type);
        
        $output = walk( $collection->findOne(array("slug"=>$slug)) );

        $app->render(200,$output);

    });


    $app->get('/(:type)/id/(:id)', function($type, $id) use ($app) {
        $collection = getDB($type);
        
        $output = $collection->findOne(array("_id"=>new MongoId($id)));
      
        $app->render(200,$output);
    });

        $app->run();

?>