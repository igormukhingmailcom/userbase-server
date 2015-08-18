<?php

namespace UserBase\Server\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraints as Assert;
use Exception;
use JWT;

class PortalController
{

    public function indexAction(Application $app, Request $request)
    {
        $data = array();
        
        $user = $app['currentuser'];
        $accountRepo = $app->getAccountRepository();
        $data['accounts'] = $accountRepo->getByUsername($user->getName());

        return new Response($app['twig']->render(
            'portal/index.html.twig',
            $data
        ));
    }
    
    public function viewAction(Application $app, Request $request, $accountname)
    {  // phpinfo();exit;
        $user = $app['currentuser'];
        $accountRepo = $app->getAccountRepository();
        $oAccount = $accountRepo->getByName($accountname);
        
        // -- GENERATE FORM --//
        $form = $app['form.factory']->createBuilder('form')
        ->add('picture', 'file', array(
            'required' => true,
            'read_only' => false,
            'label' => false,
            'trim' => true,
            'error_bubbling' => true,
            'multiple' => false,
            'constraints' => array(
                 new Assert\Image(array(
                     'minWidth' => 110,
                     'maxWidth' =>  800,
                     'minHeight' => 110,
                     'maxHeight' => 800,
                     //'mimeTypes' => array('image/jpeg', 'image/png', 'image/gif'),
                     'mimeTypesMessage' => 'Please upload a valid images',
                 )),
                new Assert\NotBlank(array('message' => 'upload picture file.'))
             ),
            'attr' => array(
            'id' => 'attachment',
            'placeholder' => 'upload Picture file',
           // 'class' => 'form-control',
            'autofocus' => '',
            )
        ))
        ->getForm();
        
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            $formData = $form->getData();
            
            if ($form->isValid()) {
                $file = $form['picture']->getData(); 
                $dir = $app['picturePath'];
                $tmpDir =  $app['tmpDirPath'];
                $newName = $accountname.'.tmp.png';
                $newResizeName = $accountname.'.png';
                $newWidth = 100;
                $newHeight = 100;
                
                $tmpName =  $accountname.'.tmp.'.$form['picture']->getData()->getClientOriginalName();
                $form['picture']->getData()->move($tmpDir, $tmpName);
                
                //CONVERT IMAGE TO PNG //
                $imgPath =  $tmpDir.'/'.$tmpName;
                
                $info = new \SplFileInfo( $imgPath);
                
                switch (strtolower($info->getExtension())) {
                    case 'gif':
                            $img = @imagecreatefromgif($imgPath);
                        break;
                    case 'jpeg':
                    case 'jpg':   
                        $img = @imagecreatefromjpeg($imgPath);
                        break;
                    case 'png':
                            $img = @imagecreatefrompng($imgPath);
                        break;
                }
                if ($img) {
                    list($width, $height) = getimagesize($imgPath);
                    
                    $im = imagecreatetruecolor($width, $height);
                    $white = imagecolorallocate($im, 255, 255, 255);  
                    imagefilledrectangle($im, 0, 0, $width, $height, $white);
                    
                    //--resize image --//
                    $resizeImag = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresized($resizeImag, $img, 0, 0, 0, 0,$newWidth, $newHeight, $width, $height);
                    imagepng($resizeImag, $dir.'/'.$newResizeName);
                    //---------//
                    imagecopy($im, $img, 0, 0, 0, 0, $width, $height);                    
                    imagepng($im, $dir.'/'.$newName);
                    
                    return $app->redirect($app['url_generator']->generate('portal_cropimag', array(
                            'accountname' => $accountname  
                        )
                    ));
                }
            }
        }
        return new Response($app['twig']->render('portal/picture.html.twig', 
            array(
                'form' => $form->createView(),
                'accountname' => $accountname,
                'oAccount' => $oAccount
            )
        ));
    }
    
    public function appLoginAction(Application $app, Request $request, $appname)
    {
        $user = $app['currentuser'];
        $accountRepo = $app->getAccountRepository();
        $account = $accountRepo->getByAppNameAndUsername($appname, $user->getName());

        $key = 'super_secret'; // TODO: make this configurable + support rsa
        $token = array(
            "iss" => 'userbase',
            "aud" => $appname,
            "iat" => time(),
            "exp" => time() + (60*10),
            "sub" => $user->getName(),
            "my_own_thing" => 'this_needs_to_be_something_sensible'
        );
        $jwt = JWT::encode($token, $key);
        
        $url = $account->getApp()->getBaseUrl();
        
        // TODO: The way of passing JWT's should be configurable per app
        $url .= '/login/jwt/' . $jwt;
        return $app->redirect($url);
        
        exit($url);
    }    
    
    public function cropImageAction(Application $app, Request $request, $accountname)
    {
        $accountRepo = $app->getAccountRepository();
        $oAccount = $accountRepo->getByName($accountname);
        
        $dir = $app['picturePath'];
        $imgName = $accountname.'.tmp.png';
        $newResizePath = $dir.'/'.$accountname.'.png';
        $imgPath = $dir.'/'.$imgName;
        
        if ($request->isMethod('POST')) {
            $newWidth = 100;
            $newHeight = 100;
            $formData = array();
            $formData['x'] = $request->get('x'); 
            $formData['y'] = $request->get('y');
            $formData['w'] = $request->get('w');
            $formData['h'] = $request->get('h');
            
            $imgSrc = imagecreatefrompng($imgPath);
            $dstSrc = ImageCreateTrueColor( $newWidth, $newHeight);
            imagecolortransparent($dstSrc, imagecolorallocatealpha($dstSrc, 0, 0, 0, 127));
            imagealphablending($dstSrc, false);
            imagesavealpha($dstSrc, true);
            imagecopyresampled($dstSrc, $imgSrc,0,0, $formData['x'], $formData['y'], $newWidth,$newHeight, $formData['w'],$formData['h']);
            @imagepng($dstSrc, $newResizePath);
            
            return $app->redirect($app['url_generator']->generate('portal_index', array()));
        }
        return new Response($app['twig']->render('portal/picture-crop.html.twig',
            array(              
                'accountname' => $accountname,
                'oAccount' => $oAccount,
                 'tmpImgPath' => '/'.$dir.'/'.$imgName
            )
         ));
    }
}
