<?php

namespace AppBundle\Controller\Api;

use AppBundle\Api\ApiProblem;
use AppBundle\Api\ApiProblemException;
use AppBundle\Controller\BaseController;
use AppBundle\Entity\Programmer;
use AppBundle\Form\ProgrammerType;
use AppBundle\Form\UpdateProgrammerType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProgrammerController extends BaseController
{
    /**
     * @Route("/api/programmers")
     * @Method("POST")
     */
    public function newAction(Request $request)
    {
        $programmer = new Programmer();
        $form = $this->createForm(new ProgrammerType(), $programmer);
        $this->processForm($request, $form);

        if (!$form->isValid()) { // checks to see if the form object is not valid. This code is deprecated in Symfony 4.0
                                // Instead use !$form->isSubmitted() && $form->isValid which will work the same way as this
            $this->throwApiProblemValidationException($form);
        }

        $programmer->setUser($this->findUserByUsername('weaverryan'));

        $em = $this->getDoctrine()->getManager();
        $em->persist($programmer);
        $em->flush();

        $response = $this->createApiResponse($programmer, 201);
        $programmerUrl = $this->generateUrl(
            'api_programmers_show',
            ['nickname' => $programmer->getNickname()]
        );
        $response->headers->set('Location', $programmerUrl);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}", name="api_programmers_show")
     * @Method("GET")
     */
    public function showAction($nickname)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if (!$programmer) {
            throw $this->createNotFoundException(sprintf(
                'No programmer found with nickname "%s"',
                $nickname
            ));
        }

        $response = $this->createApiResponse($programmer, 200);

        return $response;
    }

    /**
     * @Route("/api/programmers")
     * @Method("GET")
     */
    public function listAction()
    {
        $programmers = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findAll();

        $response = $this->createApiResponse(['programmers' => $programmers], 200);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}")
     * @Method({"PUT", "PATCH"})
     */
    public function updateAction($nickname, Request $request)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if (!$programmer) {
            throw $this->createNotFoundException(sprintf(
                'No programmer found with nickname "%s"',
                $nickname
            ));
        }

        $form = $this->createForm(new UpdateProgrammerType(), $programmer);
        $this->processForm($request, $form);

        if (!$form->isValid()) {
            $this->throwApiProblemValidationException($form);
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($programmer);
        $em->flush();

        $response = $this->createApiResponse($programmer, 200);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}")
     * @Method("DELETE")
     */
    public function deleteAction($nickname)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if ($programmer) {
            // debated point: should we 404 on an unknown nickname?
            // or should we just return a nice 204 in all cases?
            // we're doing the latter
            $em = $this->getDoctrine()->getManager();
            $em->remove($programmer);
            $em->flush();
        }

        return new Response(null, 204);
    }

    private function processForm(Request $request, FormInterface $form)
    {
        $data = json_decode($request->getContent(), true);
        if ($data === null) { // If the data passed to this method is exactly equal to null
            $apiProblem = new ApiProblem(400, ApiProblem::TYPE_INVALID_REQUEST_BODY_FORMAT); // Then use the ApiProblem object so that we get a proper response when the data is null, by setting the status code and using our const variable

            throw new ApiProblemException($apiProblem); // Throwing a new ApiProblemException and passing the $apiProblem object to it so that it formats it into a nice ApiProblem JSON response
        }

        $clearMissing = $request->getMethod() != 'PATCH';
        $form->submit($data, $clearMissing);
    }

    private function getErrorsFromForm(FormInterface $form)
    {
        $errors = array();
        foreach ($form->getErrors() as $error) { // Loops through the all the Form objects, because a Form has many objects, one for each field, and if that field has sub-field, then each one of those will be a form object as well.
            $errors[] = $error->getMessage(); // fetches the errors from each field, if they have any
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) { // Then creates an associative array of those errors
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    private function throwApiProblemValidationException(FormInterface $form)
    {
        $errors = $this->getErrorsFromForm($form);

        $apiProblem = new ApiProblem( // Using an ApiProblem object to create the array that holds the information about a validation error instead of doing it manually. For example the type and title of the error
            400, // Setting the status code to 400
            ApiProblem::TYPE_VALIDATION_ERROR // Since the validation error message should not change at all, I have set it to a constant variable in ApiProblem class and am calling it here
        );
        $apiProblem->set('errors', $errors); // Setting the extra error field that we have in our errors array to the $apiProblem object which we created above by passing it the errors string and the errors variable

        throw new ApiProblemException($apiProblem);
    }
}
