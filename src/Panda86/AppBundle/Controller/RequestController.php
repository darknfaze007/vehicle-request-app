<?php

namespace Panda86\AppBundle\Controller;

use Panda86\AppBundle\Entity\RequestLink;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Panda86\AppBundle\Entity\Request as EmpRequest;
use Panda86\AppBundle\Form\RequestType;
/**
 * Request controller.
 *
 */
class RequestController extends Controller
{
    /**
     * Shows the start page for a request
     */
    public function indexAction()
    {
        return $this->render('Panda86AppBundle:Request:start.html.twig');
    }

    /**
     * Lists all RequestEmployee entities.
     *
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();
        $entities = $em->getRepository('Panda86AppBundle:Request')->findAll();

        return $this->render('Panda86AppBundle:Request:list.html.twig', array(
            'entities' => $entities,
        ));
    }

    /**
     * Finds and displays a RequestEmployee entity.
     *
     */
    public function showAction($id, $embed = false)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('Panda86AppBundle:Request')->find($id);

        if (!$entity) {
            $this->get('session')->getFlashBag()->add(
                'error',
                'Oops! looks like something went wrong.'
            );
        }

        if($entity->getStatus() === 1)
        {
            $approved_entity = $em->getRepository('Panda86AppBundle:ApprovedRequest')->findOneBy(array('request' => $entity->getId()));
            if(!$approved_entity)
            {
                throw new \Exception('Approved request not found for req id = '.$entity->getId());
            }

            return $this->render('Panda86AppBundle:Request:show.html.twig', array(
                'embedded' => $embed,
                'entity' => $approved_entity->getRequest(),
                'vehicle' => $approved_entity->getVehicle(),
                'driver' => $approved_entity->getDriver(),
                'cab' => $approved_entity->getCab(),
                'authored_by' => $approved_entity->getApprovedBy(),
                'authored_at' => $approved_entity->getCreated()
            ));
        }
        elseif($entity->getStatus() === 2)
        {
            $disapproved_entity = $em->getRepository('Panda86AppBundle:DisapprovedRequest')->findOneBy(array('request' => $entity->getId()));
            if(!$disapproved_entity)
            {
                throw new \Exception('Disapproved request not found for req id = '.$entity->getId());
            }

            return $this->render('Panda86AppBundle:Request:show.html.twig', array(
                'embedded' => $embed,
                'entity' => $disapproved_entity->getRequest(),
                'authored_by' => $disapproved_entity->getDisapprovedBy(),
                'authored_at' => $disapproved_entity->getCreated()
            ));
        }

        return $this->render('Panda86AppBundle:Request:show.html.twig', array(
            'embedded' => $embed,
            'entity' => $entity,
        ));
    }

    /**
     * Find and display a request's details using the given random code in url
     *
     */
    public function detailsAction($code)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('Panda86AppBundle:RequestLink')->findOneBy(array('code' => $code));

        if (!$entity) {
            $this->get('session')->getFlashBag()->add(
                'error',
                'Your link is invalid or has been expired! Please try again using a valid link.'
            );
            return $this->render('Panda86AppBundle:Request:finish.html.twig');
        }

        $approved_entity = $em->getRepository('Panda86AppBundle:ApprovedRequest')->findOneBy(array('request' => $entity->getRequest()->getId()));

        if ($approved_entity) {
            return $this->render('Panda86AppBundle:Request:details.html.twig', array(
                'entity' => $approved_entity->getRequest(),
                'vehicle' => $approved_entity->getVehicle(),
                'driver' => $approved_entity->getDriver(),
                'cab' => $approved_entity->getCab()
            ));
        }

        return $this->render('Panda86AppBundle:Request:details.html.twig', array(
            'entity' => $entity->getRequest(),
        ));
    }

    /**
     * Displays a form to create a new Request entity.
     */
    public function newAction(Request $request)
    {
        $entity = new EmpRequest();

        if($request->getMethod() == 'POST')
        {
            $days = $request->request->get('no_days');
            if($days)
            {
                $entity->setDays($days);
            }
        }
        $form   = $this->createForm(new RequestType(), $entity);

        return $this->render('Panda86AppBundle:Request:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
            'days'   => $entity->getDays()

        ));
    }

    /**
     * Persist form data to database.
     */
    public function createAction(Request $request)
    {
        $entity  = new EmpRequest();
        $form = $this->createForm(new RequestType(), $entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            $flashmsg = "Your request has been sent! ";

            $code  = $entity->getLink()->getCode();
            $email = $entity->getRequester()->getEmail();

            if($this->_sendMail($email, $entity, $code))
            {
                $flashmsg .= 'An email message with a link to your request\'s details will be sent to you shortly.';
            }
            else
            {
                $this->get('session')->getFlashBag()->add(
                    'error',
                    'Email sending failed!'
                );
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                $flashmsg
            );
            return $this->render('Panda86AppBundle:Request:finish.html.twig');
        }
        // if form is not valid
        return $this->render('Panda86AppBundle:Request:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Shows request finish page
     */
    public function finishAction($mode = 'cancel')
    {
        if($mode === 'cancel')
        {
            $this->get('session')->getFlashBag()->add(
                'warning',
                'The request was cancelled!'
            );
        }
        return $this->render('Panda86AppBundle:Request:finish.html.twig');
    }

    private  function _sendMail($email, $entity, $code)
    {
        $message = \Swift_Message::newInstance()
            ->setContentType("text/html")
            ->setSubject('Vehicle request created!')
            ->setFrom('donotreply@icta.lk')
            ->setTo($email)
            ->setBody(
                $this->renderView(
                    'Panda86AppBundle:Template:request-created-email.html.twig', array(
                        'entity' => $entity,
                        'code' => $code
                     )
                )
            )
        ;
        try {
            $this->get('mailer')->send($message);
            return true;
        }
        catch(\Exception $e)
        {
            return false;
        }
    }
}
