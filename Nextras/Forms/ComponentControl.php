<?php

/**
 * This file is part of the Nextras community extensions of Nette Framework
 *
 * @license    MIT
 * @link       https://github.com/nextras
 * @author     Jan Skrasek
 */

namespace Nextras\Forms;

use Nette;
use Nette\Forms\Form;
use Nette\ComponentModel\IComponent;
use Nette\ComponentModel\RecursiveComponentIterator;
use Nette\Application\UI\BadSignalException;
use Nette\Application\UI\ISignalReceiver;
use Nette\ComponentModel\IContainer;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Container;
use Nette\Application\UI\PresenterComponentReflection;
use Nette\Application\UI\Presenter;
use Nette\Utils\Html;



/**
 * @author Jan Skrasek
 * @author Jan Tvrdík
 */
abstract class ComponentControl extends BaseControl implements ISignalReceiver, \ArrayAccess, IContainer
{
	/** @var IComponent[] */
	private $components = array();

	/** @var IComponent|NULL */
	private $cloning;



	public function __construct($caption = NULL)
	{
		parent::__construct($caption);
		$this->control = Html::el('');
	}



	/**
	 * Returns the presenter where this component belongs to.
	 * @param  bool   throw exception if presenter doesn't exist?
	 * @return Presenter|NULL
	 */
	public function getPresenter($need = TRUE)
	{
		return $this->lookup('Nette\Application\UI\Presenter', $need);
	}



	/**
	 * Returns a fully-qualified name that uniquely identifies the component
	 * within the presenter hierarchy.
	 * @return string
	 */
	public function getUniqueId()
	{
		return $this->lookupPath('Nette\Application\UI\Presenter', TRUE);
	}



	/**
	 * @return void
	 */
	protected function validateParent(Nette\ComponentModel\IContainer $parent)
	{
		parent::validateParent($parent);
		$this->monitor('Nette\Forms\Form');
	}



	/**
	 * Calls public method if exists.
	 * @param  string
	 * @param  array
	 * @return bool  does method exist?
	 */
	protected function tryCall($method, array $params)
	{
		$rc = $this->getReflection();
		if ($rc->hasMethod($method)) {
			$rm = $rc->getMethod($method);
			if ($rm->isPublic() && !$rm->isAbstract() && !$rm->isStatic()) {
				$this->checkRequirements($rm);
				$rm->invokeArgs($this, $rc->combineArgs($rm, $params));
				return TRUE;
			}
		}
		return FALSE;
	}



	/**
	 * Checks for requirements such as authorization.
	 * @return void
	 */
	public function checkRequirements($element)
	{
	}



	/**
	 * Access to reflection.
	 * @return PresenterComponentReflection
	 */
	public static function getReflection()
	{
		return new PresenterComponentReflection(get_called_class());
	}
	/** @var Nette\Templating\ITemplate */
	private $template;

	/** @var array */
	private $invalidSnippets = array();

	/** @var bool */
	public $snippetMode;



	/**
	 * Returns form control
	 * @return Nette\Utils\Html
	 */
	public function getControl()
	{
		$control = Nette\Utils\Html::el();
		$control->setHtml($this->toString());
		return $control;
	}



	/**
	 * Common render method.
	 * @return void
	 */
	protected function beforeRender()
	{
	}



	/**
	 * Renders form control
	 * @return void
	 */
	public function render()
	{
		echo $this->toString();
	}



	/**
	 * Returns rendered template
	 * @return string
	 */
	public function toString()
	{
		$this->beforeRender();
		return (string) $this->getTemplate();
	}



	/********************* template factory ****************d*g**/



	/**
	 * @return Nette\Templating\ITemplate
	 */
	final public function getTemplate()
	{
		if ($this->template === NULL) {
			$value = $this->createTemplate();
			if (!$value instanceof Nette\Templating\ITemplate && $value !== NULL) {
				$class2 = get_class($value); $class = get_class($this);
				throw new Nette\UnexpectedValueException("Object returned by $class::createTemplate() must be instance of Nette\\Templating\\ITemplate, '$class2' given.");
			}
			$this->template = $value;
		}
		return $this->template;
	}



	/**
	 * @param  string|NULL
	 * @return Nette\Templating\ITemplate
	 */
	protected function createTemplate($class = NULL)
	{
		$ref = $this->getReflection();
		$path = dirname($ref->getFileName()) . '/' . $ref->getShortName() . '.latte';

		$template = $class ? new $class : new Nette\Templating\FileTemplate($path);
		$presenter = $this->getPresenter(FALSE);
		$template->onPrepareFilters[] = $this->templatePrepareFilters;
		$template->registerHelperLoader('Nette\Templating\Helpers::loader');
		$template->form = $template->_form = $this;

		// default parameters
		$template->control = $template->_control = $this;
		$template->presenter = $template->_presenter = $presenter;
		if ($presenter instanceof Presenter) {
			$template->setCacheStorage($presenter->getContext()->{'nette.templateCacheStorage'});
			$template->user = $presenter->getUser();
			$template->netteHttpResponse = $presenter->getContext()->getByType('Nette\Http\IResponse');
			$template->netteCacheStorage = $presenter->getContext()->getByType('Nette\Caching\IStorage');
			$template->baseUri = $template->baseUrl = rtrim($presenter->getContext()->getByType('Nette\Http\IRequest')->getUrl()->getBaseUrl(), '/');
			$template->basePath = preg_replace('#https?://[^/]+#A', '', $template->baseUrl);
		}

		return $template;
	}



	/**
	 * Descendant can override this method to customize template compile-time filters.
	 * @param  Nette\Templating\Template
	 * @return void
	 */
	public function templatePrepareFilters($template)
	{
		$template->registerFilter($this->getPresenter()->getContext()->createNette__Latte());
	}



	/********************* rendering ****************d*g**/



	/**
	 * Forces control or its snippet to repaint.
	 * @param  string
	 * @return void
	 */
	public function invalidateControl($snippet = NULL)
	{
		$this->invalidSnippets[$snippet] = TRUE;
	}



	/**
	 * Allows control or its snippet to not repaint.
	 * @param  string
	 * @return void
	 */
	public function validateControl($snippet = NULL)
	{
		if ($snippet === NULL) {
			$this->invalidSnippets = array();

		} else {
			unset($this->invalidSnippets[$snippet]);
		}
	}



	/**
	 * Is required to repaint the control or its snippet?
	 * @param  string  snippet name
	 * @return bool
	 */
	public function isControlInvalid($snippet = NULL)
	{
		if ($snippet === NULL) {
			if (count($this->invalidSnippets) > 0) {
				return TRUE;

			} else {
				$queue = array($this);
				do {
					foreach (array_shift($queue)->getComponents() as $component) {
						if ($component instanceof IRenderable) {
							if ($component->isControlInvalid()) {
								// $this->invalidSnippets['__child'] = TRUE; // as cache
								return TRUE;
							}

						} elseif ($component instanceof Nette\ComponentModel\IContainer) {
							$queue[] = $component;
						}
					}
				} while ($queue);

				return FALSE;
			}

		} else {
			return isset($this->invalidSnippets[NULL]) || isset($this->invalidSnippets[$snippet]);
		}
	}



	/**
	 * Returns snippet HTML ID.
	 * @param  string  snippet name
	 * @return string
	 */
	public function getSnippetId($name = NULL)
	{
		// HTML 4 ID & NAME: [A-Za-z][A-Za-z0-9:_.-]*
		return 'snippet-' . $this->getUniqueId() . '-' . $name;
	}



	/********************* interface ISignalReceiver ****************d*g**/



	/**
	 * Calls signal handler method.
	 * @param  string
	 * @return void
	 * @throws BadSignalException if there is not handler method
	 */
	public function signalReceived($signal)
	{
		if (!$this->tryCall($this->formatSignalMethod($signal), $this->params)) {
			$class = get_class($this);
			throw new BadSignalException("There is no handler for signal '$signal' in class $class.");
		}
	}



	/**
	 * Formats signal handler method name -> case sensitivity doesn't matter.
	 * @param  string
	 * @return string
	 */
	public function formatSignalMethod($signal)
	{
		return $signal == NULL ? NULL : 'handle' . $signal; // intentionally ==
	}



	/********************* interface IContainer ****************d*g**/



	/**
	 * Adds the specified component to the IContainer.
	 * @param  IComponent
	 * @param  string
	 * @param  string
	 * @return Container  provides a fluent interface
	 * @throws Nette\InvalidStateException
	 */
	public function addComponent(IComponent $component, $name, $insertBefore = NULL)
	{
		if ($name === NULL) {
			$name = $component->getName();
		}

		if (is_int($name)) {
			$name = (string) $name;

		} elseif (!is_string($name)) {
			throw new Nette\InvalidArgumentException("Component name must be integer or string, " . gettype($name) . " given.");

		} elseif (!preg_match('#^[a-zA-Z0-9_]+\z#', $name)) {
			throw new Nette\InvalidArgumentException("Component name must be non-empty alphanumeric string, '$name' given.");
		}

		if (isset($this->components[$name])) {
			throw new Nette\InvalidStateException("Component with name '$name' already exists.");
		}

		// check circular reference
		$obj = $this;
		do {
			if ($obj === $component) {
				throw new Nette\InvalidStateException("Circular reference detected while adding component '$name'.");
			}
			$obj = $obj->getParent();
		} while ($obj !== NULL);

		// user checking
		$this->validateChildComponent($component);

		try {
			if (isset($this->components[$insertBefore])) {
				$tmp = array();
				foreach ($this->components as $k => $v) {
					if ($k === $insertBefore) {
						$tmp[$name] = $component;
					}
					$tmp[$k] = $v;
				}
				$this->components = $tmp;
			} else {
				$this->components[$name] = $component;
			}
			$component->setParent($this, $name);

		} catch (\Exception $e) {
			unset($this->components[$name]); // undo
			throw $e;
		}
		return $this;
	}



	/**
	 * Removes a component from the IContainer.
	 * @return void
	 */
	public function removeComponent(IComponent $component)
	{
		$name = $component->getName();
		if (!isset($this->components[$name]) || $this->components[$name] !== $component) {
			throw new Nette\InvalidArgumentException("Component named '$name' is not located in this container.");
		}

		unset($this->components[$name]);
		$component->setParent(NULL);
	}



	/**
	 * Returns component specified by name or path.
	 * @param  string
	 * @param  bool   throw exception if component doesn't exist?
	 * @return IComponent|NULL
	 */
	final public function getComponent($name, $need = TRUE)
	{
		if (is_int($name)) {
			$name = (string) $name;

		} elseif (!is_string($name)) {
			throw new Nette\InvalidArgumentException("Component name must be integer or string, " . gettype($name) . " given.");

		} else {
			$a = strpos($name, self::NAME_SEPARATOR);
			if ($a !== FALSE) {
				$ext = (string) substr($name, $a + 1);
				$name = substr($name, 0, $a);
			}

			if ($name === '') {
				throw new Nette\InvalidArgumentException("Component or subcomponent name must not be empty string.");
			}
		}

		if (!isset($this->components[$name])) {
			$component = $this->createComponent($name);
			if ($component instanceof IComponent && $component->getParent() === NULL) {
				$this->addComponent($component, $name);
			}
		}

		if (isset($this->components[$name])) {
			if (!isset($ext)) {
				return $this->components[$name];

			} elseif ($this->components[$name] instanceof IContainer) {
				return $this->components[$name]->getComponent($ext, $need);

			} elseif ($need) {
				throw new Nette\InvalidArgumentException("Component with name '$name' is not container and cannot have '$ext' component.");
			}

		} elseif ($need) {
			throw new Nette\InvalidArgumentException("Component with name '$name' does not exist.");
		}
	}



	/**
	 * Component factory. Delegates the creation of components to a createComponent<Name> method.
	 * @param  string      component name
	 * @return IComponent  the created component (optionally)
	 */
	protected function createComponent($name)
	{
		$ucname = ucfirst($name);
		$method = 'createComponent' . $ucname;
		if ($ucname !== $name && method_exists($this, $method) && $this->getReflection()->getMethod($method)->getName() === $method) {
			$component = $this->$method($name);
			if (!$component instanceof IComponent && !isset($this->components[$name])) {
				$class = get_class($this);
				throw new Nette\UnexpectedValueException("Method $class::$method() did not return or create the desired component.");
			}
			return $component;
		}
	}



	/**
	 * Iterates over a components.
	 * @param  bool    recursive?
	 * @param  string  class types filter
	 * @return \ArrayIterator
	 */
	final public function getComponents($deep = FALSE, $filterType = NULL)
	{
		$iterator = new RecursiveComponentIterator($this->components);
		if ($deep) {
			$deep = $deep > 0 ? \RecursiveIteratorIterator::SELF_FIRST : \RecursiveIteratorIterator::CHILD_FIRST;
			$iterator = new \RecursiveIteratorIterator($iterator, $deep);
		}
		if ($filterType) {
			$iterator = new Nette\Iterators\Filter($iterator, function($item) use ($filterType) {
				return $item instanceof $filterType;
			});
		}
		return $iterator;
	}



	/**
	 * Descendant can override this method to disallow insert a child by throwing an Nette\InvalidStateException.
	 * @return void
	 * @throws Nette\InvalidStateException
	 */
	protected function validateChildComponent(IComponent $child)
	{
	}



	/********************* interface \ArrayAccess ****************d*g**/



	/**
	 * Adds the component to the container.
	 * @param  string  component name
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	final public function offsetSet($name, $component)
	{
		$this->addComponent($component, $name);
	}



	/**
	 * Returns component specified by name. Throws exception if component doesn't exist.
	 * @param  string  component name
	 * @return Nette\ComponentModel\IComponent
	 * @throws Nette\InvalidArgumentException
	 */
	final public function offsetGet($name)
	{
		return $this->getComponent($name, TRUE);
	}



	/**
	 * Does component specified by name exists?
	 * @param  string  component name
	 * @return bool
	 */
	final public function offsetExists($name)
	{
		return $this->getComponent($name, FALSE) !== NULL;
	}



	/**
	 * Removes component from the container.
	 * @param  string  component name
	 * @return void
	 */
	final public function offsetUnset($name)
	{
		$component = $this->getComponent($name, FALSE);
		if ($component !== NULL) {
			$this->removeComponent($component);
		}
	}



	/********************* cloneable, serializable ****************d*g**/



	/**
	 * Object cloning.
	 */
	public function __clone()
	{
		if ($this->components) {
			$oldMyself = reset($this->components)->getParent();
			$oldMyself->cloning = $this;
			foreach ($this->components as $name => $component) {
				$this->components[$name] = clone $component;
			}
			$oldMyself->cloning = NULL;
		}
		parent::__clone();
	}



	/**
	 * Is container cloning now?
	 * @return NULL|IComponent
	 * @internal
	 */
	public function _isCloning()
	{
		return $this->cloning;
	}

}
