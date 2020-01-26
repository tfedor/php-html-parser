<?php 

declare(strict_types=1);

namespace PHPHtmlParser\Dom;

use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\LogicalException;
use stringEncode\Encode;

/**
 * Inner node of the html tree, might have children.
 *
 * @package PHPHtmlParser\Dom
 */
abstract class InnerNode extends ArrayNode
{

    /**
     * An array of all the children.
     *
     * @var ChildrenCollection
     */
    protected $children;

    public function __construct() {
        parent::__construct();
        $this->children = new ChildrenCollection();
    }

    /**
     * Sets the encoding class to this node and propagates it
     * to all its children.
     *
     * @param Encode $encode
     *
     * @return void
     */
    public function propagateEncoding(Encode $encode): void
    {
        $this->encode = $encode;
        $this->tag->setEncoding($encode);
        // check children
        foreach ($this->children as $child) {
            /** @var AbstractNode $node */
            $node = $child['node'];
            $node->propagateEncoding($encode);
        }
    }

    /**
     * Checks if this node has children.
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    /**
     * Returns the child by id.
     *
     * @param int $id
     *
     * @return AbstractNode
     * @throws ChildNotFoundException
     */
    public function getChild(int $id): AbstractNode
    {
        if (!isset($this->children[$id])) {
            throw new ChildNotFoundException("Child '$id' not found in this node.");
        }

        return $this->children[$id]['node'];
    }

    /**
     * Returns a new array of child nodes
     *
     * @return array
     */
    public function getChildren(): array
    {
        $nodes = [];
        $childrenIds = [];
        try {
            $child = $this->firstChild();
            do {
                $nodes[] = $child;
                $childrenIds[] = $child->id;
                $child = $this->nextChild($child->id());
                if (in_array($child->id, $childrenIds, true)) {
                    throw new CircularException('Circular sibling referance found. Child with id '.$child->id().' found twice.');
                }
            } while (true);
        } catch (ChildNotFoundException $e) {
            // we are done looking for children
            unset($e);
        }

        return $nodes;
    }

    /**
     * Counts children
     *
     * @return int
     */
    public function countChildren(): int
    {
        return count($this->children);
    }

    /**
     * Adds a child node to this node and returns the id of the child for this
     * parent.
     * @param AbstractNode $child
     * @param int          $before
     * @return bool
     * @throws ChildNotFoundException
     * @throws CircularException
     */
    public function addChild(AbstractNode $child, int $before = -1): bool
    {
        $prev = null;

        // check integrity
        if ($this->isAncestor($child->id())) {
            throw new CircularException('Can not add child. It is my ancestor.');
        }

        // check if child is itself
        if ($child->id() == $this->id) {
            throw new CircularException('Can not set itself as a child.');
        }

        $next = null;

        if ($this->hasChildren()) {
            if (isset($this->children[$child->id()])) {
                // we already have this child
                return false;
            }

            if ($before >= 0) {
                if (!isset($this->children[$before])) {
                    return false;
                }

                $prev = $this->children[$before]['prev'];

                if ($prev) {
                    $this->children->setNext($prev, $child->id());
                }

                $this->children->setPrev($before, $child->id());
                $next = $before;
            } else {
                $sibling = $this->lastChild();
                $prev = $sibling->id();

                $this->children->setNext($prev, $child->id());
            }
        }

        $this->children[$child->id()] = [
          'node' => $child,
          'next' => $next,
          'prev' => $prev,
        ];

        // tell child I am the new parent
        $child->setParent($this);

        //clear any cache
        $this->clear();

        return true;
    }

    /**
     * Insert element before child with provided id
     * @param AbstractNode $child
     * @param int          $id
     * @return bool
     * @throws ChildNotFoundException
     * @throws CircularException
     */
    public function insertBefore(AbstractNode $child, int $id): bool
    {
        return $this->addChild($child, $id);
    }

    /**
     * Insert element before after with provided id
     * @param AbstractNode $child
     * @param int          $id
     * @return bool
     * @throws ChildNotFoundException
     * @throws CircularException
     */
    public function insertAfter(AbstractNode $child, int $id): bool
    {
        if (!isset($this->children[$id])) {
            return false;
        }

        if (isset($this->children[$id]['next']) && is_int($this->children[$id]['next'])) {
            return $this->addChild($child, (int) $this->children[$id]['next']);
        }

        // clear cache
        $this->clear();

        return $this->addChild($child);
    }

    /**
     * Removes the child by id.
     *
     * @param int $id
     *
     * @return InnerNode
     * @chainable
     */
    public function removeChild(int $id): InnerNode
    {
        if (!isset($this->children[$id])) {
            return $this;
        }

        // handle moving next and previous assignments.
        $next = $this->children[$id]['next'];
        $prev = $this->children[$id]['prev'];
        if (!is_null($next)) {
            $this->children->setPrev($next, $prev);
        }
        if (!is_null($prev)) {
            $this->children->setNext($prev, $next);
        }

        // remove the child
        unset($this->children[$id]);

        //clear any cache
        $this->clear();

        return $this;
    }

    /**
     * Check if has next Child
     *
     * @param int $id
     *
     * @return mixed
     * @throws ChildNotFoundException
     */
    public function hasNextChild(int $id)
    {
        $child = $this->getChild($id);
        return $this->children[$child->id()]['next'];
    }

    /**
     * Attempts to get the next child.
     *
     * @param int $id
     *
     * @return AbstractNode
     * @throws ChildNotFoundException
     * @uses $this->getChild()
     */
    public function nextChild(int $id): AbstractNode
    {
        $child = $this->getChild($id);
        $next = $this->children[$child->id()]['next'];
        if (is_null($next) || !is_int($next)) {
            throw new ChildNotFoundException("Child '$id' next sibling not found in this node.");
        }

        return $this->getChild($next);
    }

    /**
     * Attempts to get the previous child.
     *
     * @param int $id
     *
     * @return AbstractNode
     * @throws ChildNotFoundException
     * @uses $this->getChild()
     */
    public function previousChild(int $id): AbstractNode
    {
        $child = $this->getchild($id);
        $next = $this->children[$child->id()]['prev'];
        if (is_null($next) || !is_int($next)) {
            throw new ChildNotFoundException("Child '$id' previous not found in this node.");
        }

        return $this->getChild($next);
    }

    /**
     * Checks if the given node id is a child of the
     * current node.
     *
     * @param int $id
     *
     * @return bool
     */
    public function isChild(int $id): bool
    {
        return isset($this->children[$id]);
    }

    /**
     * Removes the child with id $childId and replace it with the new child
     * $newChild.
     *
     * @param int          $childId
     * @param AbstractNode $newChild
     *
     * @return void
     */
    public function replaceChild(int $childId, AbstractNode $newChild): void
    {
        $oldChild = $this->children[$childId];

        $newChild->prev = (int) $oldChild['prev'];
        $newChild->next = (int) $oldChild['next'];

        $this->children[$newChild->id()] = [
          'prev' => $oldChild['prev'],
          'node' => $newChild,
          'next' => $oldChild['next']
        ];

        // change previous child id to new child
        if ($oldChild['prev'] && isset($this->children[$newChild->prev])) {
            $this->children->setNext($oldChild['prev'], $newChild->id());
        }

        // change next child id to new child
        if ($oldChild['next'] && isset($this->children[$newChild->next])) {
            $this->children->setPrev($oldChild['next'], $newChild->id());
        }

        // remove old child
        unset($this->children[$childId]);

        // clean out cache
        $this->clear();
    }

    /**
     * Shortcut to return the first child.
     *
     * @return AbstractNode
     * @throws ChildNotFoundException
     * @uses $this->getChild()
     */
    public function firstChild(): AbstractNode
    {
        if (count($this->children) == 0) {
            // no children
            throw new ChildNotFoundException("No children found in node.");
        }

        $key = $this->children->getFirstKey();
        return $this->getChild($key);
    }

    /**
     * Attempts to get the last child.
     *
     * @return AbstractNode
     * @throws ChildNotFoundException
     * @uses $this->getChild()
     */
    public function lastChild(): AbstractNode
    {
        if (count($this->children) == 0) {
            // no children
            throw new ChildNotFoundException("No children found in node.");
        }

        $key = $this->children->getLastKey();

        if (!is_int($key)) {
            throw new LogicalException("Children array contain child with a key that is not an int.");
        }

        return $this->getChild($key);
    }

    /**
     * Checks if the given node id is a descendant of the
     * current node.
     *
     * @param int $id
     *
     * @return bool
     */
    public function isDescendant(int $id): bool
    {
        if ($this->isChild($id)) {
            return true;
        }

        foreach ($this->children as $child) {
            /** @var InnerNode $node */
            $node = $child['node'];
            if ($node instanceof InnerNode
              && $node->hasChildren()
              && $node->isDescendant($id)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the parent node.
     * @param InnerNode $parent
     * @return AbstractNode
     * @throws ChildNotFoundException
     * @throws CircularException
     */
    public function setParent(InnerNode $parent): AbstractNode
    {
        // check integrity
        if ($this->isDescendant($parent->id())) {
            throw new CircularException('Can not add descendant "'
              . $parent->id() . '" as my parent.');
        }

        // clear cache
        $this->clear();

        return parent::setParent($parent);
    }
}
