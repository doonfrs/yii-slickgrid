<?php
/**
 * SlickGrid class file.
 *
 * @author Steven Brendtro & Webviously, Inc.
 * @link http://webviously.net/
 * 
 * @wrapper extension for SlickGrid
 */

class CSlickGrid extends CWidget
{
	/**
	 * Default CSS class for the tab container
	 */
	const CSS_CLASS='slickgrid';

	/**
	 * @var array htmlOptions Used for customizing the grid's div container
	 */
	public $htmlOptions=array();

	public $loadJQuery = false;

	/**
	 * @var array columns Specifies columns to use when building the grid
	 */
	public $columns=array();

	/**
	 * @var string gridId Used to specify the ID tag of the grid's div container
	 */
	public $gridId = 'myGrid';
	
	/**
	 * @var array slickgridOptions for customizing SlickGrid's behavior
	 */
	public $slickgridOptions=array();

	/**
	 * @var array slickgridPlugins for loading SlickGrid plugins
	 */
	public $slickgridPlugins=array();

	/**
	 * @var array defaultColumnOptions for setting default options on columns
	 */
	public $defaultColumnOptions=array();

	/**
	 * @var array slickgridColumn specifications for the grid
	 */
	public $slickgridColumns=array();

	/**
	 * @var mixed Data provider for the grid
	 */
	public $dataProvider;

	/**
	 * @var boolean Enable Ajax mode for the grid
	 */
	public $enableAjax = false;

	/**
	 * @var string URL to use for fetching Ajax data
	 */
	public $ajaxDataUrl = '';

	/**
	 * @var string URL to the extension's assets
	 */
	private $_baseAssetsUrl;
	
	/**
	 * Runs the widget.
	 */
	public function run()
	{

		if ( $this->enableAjax && ! $this->ajaxDataUrl )
		{
			$this->ajaxDataUrl = CHtml::normalizeUrl(array(Yii::app()->controller->getId().'/'.Yii::app()->controller->getAction()->getId()));
		}

		$this->initColumns();

		// If this is an ajax request and the ajax parameter matches the grid id, only return the data and exit
		if ( $this->enableAjax && Yii::app()->request->getParam( 'ajax' ) == $this->gridId )
		{
			// Adjust the pagination for the specified details
			$pagination = $this->dataProvider->pagination;
			$pagination->setPageSize( Yii::app()->request->getParam( 'count' ) );
			$pagination->setCurrentPage( Yii::app()->request->getParam( 'page' ) );
			// Force recalculation of pages, as they may have been changed from their default settings
			$pagination->getCurrentPage( true );

			ob_end_clean();
			header('Content-type: application/json');
			echo $this->prepareSlickGridData( true );
			Yii::app()->end();
		}
		else
		{
			$this->htmlOptions[ 'id' ] = $this->gridId;
			$this->registerClientScript();
			echo CHtml::openTag('div',$this->htmlOptions)."\n";
			echo CHtml::closeTag('div');
			$this->initGrid();
		}
	}

	/**
	 * Initializes the SlickGrid columns from the specified column list
	 */
	public function initColumns()
	{
		$columnLabels = array();
		if ( $this->columns === array() )
		{
			if ( $this->dataProvider instanceof CActiveDataProvider )
			{
				$this->columns = $this->dataProvider->model->attributeNames();
				$columnLabels = $this->dataProvider->model->attributeLabels();
			}
		}
		if ( $this->slickgridColumns === array() )
		{
			if ( $this->dataProvider instanceof CActiveDataProvider )
			{
				$columnLabels = $this->dataProvider->model->attributeLabels();
			}
			foreach ( $this->columns as $column )
			{
				if ( isset( $columnLabels[ $column ] ) )
				{
					$label = $columnLabels[ $column ];
				}
				else
				{
					$label = $column;
				}
				$columnDef =  array( 
					'id' => $column, 
					'name' => $label,
					'field' => $column
				);
				$this->slickgridColumns[] = array_merge( $columnDef, $this->defaultColumnOptions );
			}
		}
	}

	/**
	 * Registers the needed CSS and JavaScript.
	 */
	public function registerClientScript()
	{
		$assets_path = dirname(__FILE__) . '/assets';

		$this->_baseAssetsUrl = Yii::app()->assetManager->publish( $assets_path );
	
		$cs=Yii::app()->getClientScript();

		if ( $this->loadJQuery )
		{
			$cs->registerCoreScript('jquery');
			$this->registerAsset('slickgrid/lib/jquery-ui-1.8.16.custom.min.js');
		}
		$this->registerAsset('slickgrid/lib/jquery.event.drag-2.0.min.js');

		if ( $this->enableAjax )
		{
			$this->registerAsset('slickgrid/lib/jquery.jsonp-1.1.0.min.js');
		}

		$this->registerPlugins();
		
		// Register the JavaScript and CSS for SlickGrid
		$this->registerAsset('slickgrid/slick.grid.css');
		if ( $this->loadJQuery )
		{
			$this->registerAsset('slickgrid/css/smoothness/jquery-ui-1.8.16.custom.css');
		}
		$this->registerAsset('slickgrid/examples/examples.css');
		$this->registerAsset('slickgrid/slick.core.js');
		if ( $this->enableAjax )
		{
			$this->registerAsset('slick.yii.remotemodel.js');
			$this->registerAsset('slick.yii.css');
		}
		$this->registerAsset('slickgrid/slick.grid.js');
	}

	/**
	 * Register Plugins
	 */
	public function registerPlugins()
	{
		foreach ( $this->slickgridPlugins as $plugin => $initCode )
		{
			$this->registerAsset('slickgrid/plugins/slick.'.$plugin.'.js');
		}
	}

	/**
	 * Initialize Plugins
	 */
	public function initPlugins()
	{
		$output = '';
		foreach ( $this->slickgridPlugins as $plugin => $initCode )
		{
			$output .= $initCode . PHP_EOL;
		}
		return $output;
	}

	/**
	 * Initializes the grid
	 */
	public function initGrid()
	{
		$cs=Yii::app()->getClientScript();
		$columns = $this->prepareSlickGridColumns();
		$options = $this->prepareSlickGridOptions();
		$gridSetupVar = $this->gridId . '_setup';
		$columnsVar = $gridSetupVar . "['columns']";
		$optionsVar = $gridSetupVar . "['options']";
		$dataVar = $gridSetupVar . "['" . ( $this->enableAjax ? 'loader' : 'data' ) . "']";
		$ajaxOptions = '';

		if ( $this->enableAjax )
		{
			$data = 'new Slick.Data.RemoteModel()';
			$dataVarForGrid = $dataVar . '.data';
			// Set the grid ID on the remote data model
			$ajaxOptions = $dataVar . '.gridId = \'' . $this->gridId . '\';';
		}
		else
		{
			$data = $this->prepareSlickGridData( true );
			$dataVarForGrid = $dataVar . '.rows';
		}

		if ( $this->enableAjax )
		{
			$onLoad = '
			$(window).load(function() {
				' . $gridSetupVar . '[\'grid\'] = new Slick.Grid( \'#' . $this->gridId . '\', ' . $dataVarForGrid . ', ' . $columnsVar . ', ' . $optionsVar . ' );
				var grid = ' . $gridSetupVar . '[\'grid\'];
				var loader = ' . $gridSetupVar . '[\'loader\'];
				var loadingIndicator = ' . $gridSetupVar . '[\'loadingIndicator\'];
				' . $this->initPlugins() . '
				grid.updateRowCount();
				grid.render();
				
				grid.onViewportChanged.subscribe(function (e, args) {
					var vp = grid.getViewport();
					loader.ensureData(vp.top, vp.bottom);
				});

				grid.onSort.subscribe(function (e, args) {
					loader.setSort(args.sortCol.field, args.sortAsc ? 1 : -1);
					var vp = grid.getViewport();
					loader.ensureData(vp.top, vp.bottom);
				});

				loader.onDataLoading.subscribe(function () {
					if (!loadingIndicator) {
						loadingIndicator = $("<span class=\'loading-indicator\'><label>Buffering...</label></span>").appendTo(document.body);
						var $g = $("#myGrid");

						loadingIndicator
							.css("position", "absolute")
							.css("top", $g.position().top + $g.height() / 2 - loadingIndicator.height() / 2)
							.css("left", $g.position().left + $g.width() / 2 - loadingIndicator.width() / 2);
					}

					loadingIndicator.show();
				});

				loader.onDataLoaded.subscribe(function (e, args) {
					for (var i = args.from; i <= args.to; i++) {
						grid.invalidateRow(i);
					}
					grid.updateRowCount();
					grid.render();
					loadingIndicator.fadeOut();
				});

				$("#txtSearch").keyup(function (e) {
					if (e.which == 13) {
						loader.setSearch($(this).val());
						var vp = grid.getViewport();
						loader.ensureData(vp.top, vp.bottom);
					}
				});

				// load the first page
				grid.onViewportChanged.notify();
			});
			';
		}
		else
		{
			$onLoad = '
			$(window).load(function() {
				' . $gridSetupVar . '[\'grid\'] = new Slick.Grid( \'#' . $this->gridId . '\', ' . $dataVarForGrid . ', ' . $columnsVar . ', ' . $optionsVar . ' );
				var grid = ' . $gridSetupVar . '[\'grid\'];
				' . $this->initPlugins() . '
				grid.updateRowCount();
				grid.render();
			});
			';
		}
		
		// Provide the JavaScript to initialize the Grid
		$cs->registerScript(
		  'slickgrid_loader',
		  '
			var ' . $gridSetupVar . ' = [];
			var slickGridDataSource = \'' . $this->ajaxDataUrl . '\';
			' . $columnsVar . ' = ' . $columns . ';
			' . $optionsVar . ' = ' . $options . ';
			' . $dataVar . ' = ' . $data . ';
			' . $ajaxOptions . '
			' . $gridSetupVar . '[\'loadingIndicator\'];
			' . $onLoad . '
		  ',
		  CClientScript::POS_END
		);		
	}

	/**
	 * Prepare SlickGrid's data
	 */
	protected function prepareSlickGridData( $ajaxData=false )
	{
		$response = '';
		$rowCount = 0;
		if ( ! $this->dataProvider || ! $this->dataProvider->getData( true ) )  // getData( true)  because we need to recalculate for the current pager settings
		{
			$gridData = array();
		}
		else
		{

			$gridData = array();
			foreach ( $this->dataProvider->getData() as $data )
			{
				$row = array();
				foreach ( $this->slickgridColumns as $column )
				{
					$fieldName = $column['field'];
					$value = $data->{ $fieldName };
					$row[ $fieldName ] = $value;
				}
				$gridData[] = $row;
				$rowCount++;
			}
		}

		if ( $ajaxData )
		{
			$responseData = array(
				'count' => $rowCount,
				'timestamp' => time(),
				'total' => $this->dataProvider->totalItemCount,
				'rows' => $gridData
			);
			$response = Yii::app()->request->getParam('callback') . '(' . json_encode( $responseData ) . ');';
		}
		else
		{
			$response = json_encode( $gridData );
		}

		return $response;
	}
	
	/**
	 * Prepare SlickGrid's slickgridOptions.
	 */
	protected function prepareSlickGridOptions()
	{
		return json_encode( $this->slickgridOptions );
	}

	/**
	 * Prepare SlickGrid's columns
	 */
	protected function prepareSlickGridColumns()
	{
		return json_encode( $this->slickgridColumns );
	}
	
	/**
	 * generic function to register css or js
	 */
	protected function registerAsset($file)
	{
		$asset_path = $this->_baseAssetsUrl . '/' . $file;
		if(strpos($file, '.js') !== false)
			return Yii::app()->clientScript->registerScriptFile($asset_path);
		else if(strpos($file, '.css') !== false)
			return Yii::app()->clientScript->registerCssFile($asset_path);

		return $asset_path;
	}	
}
