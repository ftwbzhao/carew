<?php

namespace Carew\Twig\NodeVisitor;

use Carew\Twig\Node\Pagination as PaginationNode;

class Paginator implements \Twig_NodeVisitorInterface
{
    private $currentModule;
    private $currentRenderDocuments;
    private $maxPerPage;

    public function __construct($maxPerPage = 10)
    {
        $this->maxPerPage = $maxPerPage;
    }

    public function enterNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        if ($node instanceof \Twig_Node_Module) {
            $this->currentModule = $node;
        } elseif ($node instanceof \Twig_Node_Expression_Function) {
            $name = $node->getAttribute('name');
            if ('paginate' == $name) {
                return $this->enterPaginationFilterNode($node, $env);
            }
            if ('render_documents' == $name) {
                $this->currentRenderDocuments = $node;
            }
        }

        return $node;
    }

    public function enterPaginationFilterNode(\Twig_Node_Expression_Function $node, \Twig_Environment $env)
    {
        $args = $node->getNode('arguments');

        if (!$args->hasNode(0)) {
            throw new \Twig_Error_Syntax('Missing first argument of "paginate" function.');
        }

        // extract $maxPerPage;
        if ($args->hasNode(1)) {
            $arg = $args->getNode(1);
            if (!$arg instanceof \Twig_Node_Expression_Constant) {
                throw new \Twig_Error_Syntax('Second argument of "paginate" function should be an integer.');
            }
            $maxPerPage = (integer) $arg->getAttribute('value');
        } else {
            $maxPerPage = $this->maxPerPage;
        }

        $this->alterRenderDocumentsWithPagination();

        $nodeToPaginate = $args->getNode(0);

        // Set-up the PaginationNode
        $extra = $this->currentModule->getNode('extra');
        $extra->setNode(0, new PaginationNode($nodeToPaginate, $maxPerPage));

        // Filter the node with "|slice(offset, maxPerPage)"
        $slicedNode = new \Twig_Node_Expression_Filter(
            $nodeToPaginate,
            new \Twig_Node_Expression_Constant('slice', 1),
            new \Twig_Node(array(
                new \Twig_Node_Expression_Name('__offset__', 1),
                new \Twig_Node_Expression_Constant($maxPerPage, 1),
            )),
            1
        );

        return $slicedNode;
    }

    public function leaveNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        if ($node instanceof \Twig_Node_Module) {
            $this->currentModule = null;
        } elseif ($node instanceof \Twig_Node_Expression_Function) {
            $name = $node->getAttribute('name');
            if ('render_documents' == $name) {
                $this->currentRenderDocuments = null;
            }
        }

        return $node;
    }

    public function getPriority()
    {
        0;
    }

    private function alterRenderDocumentsWithPagination()
    {
        if (!$this->currentRenderDocuments) {
            return;
        }

        $args = $this->currentRenderDocuments->getNode('arguments');
        $args->setNode(1, new \Twig_Node_Expression_Name('__pages__', 1));
        $args->setNode(2, new \Twig_Node_Expression_Name('__current_page__', 1));
    }
}