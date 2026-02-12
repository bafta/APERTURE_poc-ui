<?

function arrayToAttributes($arr) {
    return implode(' ', array_map(fn($k, $v) => sprintf('%s="%s"', htmlspecialchars($k, ENT_QUOTES), htmlspecialchars($v, ENT_QUOTES)), array_keys($arr), $arr));
}

function updateExtraFromAttribute($stuff, $attr, $extra) {
    if(empty($extra)) return '';
    $extra = preg_replace('/('.$attr.'="[^"]*)/', '$1 '.$stuff.'', $extra, 1, $count);
    if ($count === 0) { $extra.= ' '.$attr.'="'.$stuff.'" '; }
    return $extra;
}

/**
 * DON'T pass $label through cms() or cmsAdmin() at the call site as it gets cms'd in the output below
 *
 */

function displayFieldSetformField($label,$ui,$attr) {
    $helpText = '';
    if(!empty($attr['helpText'])) {
        $helpText = htmlspecialchars($attr['helpText']);
    } else if(!empty($attr['unsafe__helpText'])) {
        $helpText = $attr['unsafe__helpText'];
    }
    $divClasses = 'fieldset__field';
    if (!empty($attr['class'])) {
        $divClasses .= ' ' . $attr['class'];
    }
    $inputWrapClass = '';
    if (!empty($attr['inputWrapClass'])) {
        $inputWrapClass = $attr['inputWrapClass'];
    }

    $extra = '';
    if (!empty($attr['extra'])) {
        $extra = $attr['extra'];

        // Extract 'class' from 'extra' if it exists and remove it
        if (preg_match('/class="([^"]*)"/', $extra, $matches)) {
            $divClasses .= ' ' . $matches[1]; // Append the extracted class to $divClasses
            $extra = preg_replace('/\s*class="[^"]*"\s*/', ' ', $extra); // Remove the class attribute
        }
    }

    $isWYSIWIGS = preg_match('/\bclass=[\'"][^\'"]*\bredactor\b[^\'"]*[\'"]/i',$ui);
    echo '
    <div class="'.$divClasses.'" '.$extra.'>
        <div></div>
        <div>
            <label>'.cmsAdmin($label).'</label>
            '.(($isWYSIWIGS)?$ui:'').'
            <span class="help-text">'.$helpText.'</span>
        </div>
        <div class="'.$inputWrapClass.'">'.(($isWYSIWIGS)?'':$ui).'</div>
    </div>
    ';
}
function formLabel($for,$label,$attr,$contentCallback) {
    global $isFieldSetPage;
    $isFieldSetPage = $isFieldSetPage??false;
    $args = func_get_args();
    $parentAttr = array_splice($args,2,1)[0] ?? []; //Gets parent level attributtes from args
    if(empty($parentAttr) || !is_array($parentAttr)) {
        $parentAttr = [];
    }
    array_splice($args,2,1); //Gets function callback from args
    ob_start();
    $result = call_user_func_array($contentCallback,$args);
    $ui=ob_get_contents();
    ob_end_clean();
    if($isFieldSetPage) {
        return displayFieldSetformField($label,$ui,$parentAttr);
    }
    if ($result === false) {
        // Shows nothing but it could include an error we need to display
        echo $ui;
    } else {
        global $USER_TYPE;
        
        $divClasses = "question";
        // Copy any required class from the input to the label
        if (preg_match('/\bclass=[\'"][^\'"]*\brequired\b[^\'"]*[\'"]/i',$ui)) {
            $divClasses.=" required";
        }

        if($USER_TYPE == 'adminUser') {
            $divClasses.=' form-field';
            if(preg_match('/\bclass=[\'"][^\'"]*\bredactor\b[^\'"]*[\'"]/i',$ui)) {
                $divClasses.=' form-field--expanded';
            }
        }

        // Check if 'class' exists in $parentAttr and add it to $divClasses
        if (!empty($parentAttr['class'])) {
            $divClasses .= ' ' . $parentAttr['class'];
        }

        // Initialize $extra
        $extra = '';
        if (!empty($parentAttr['extra'])) {
            $extra = $parentAttr['extra'];

            // Extract 'class' from 'extra' if it exists and remove it
            if (preg_match('/class="([^"]*)"/', $extra, $matches)) {
                $divClasses .= ' ' . $matches[1]; // Append the extracted class to $divClasses
                $extra = preg_replace('/\s*class="[^"]*"\s*/', ' ', $extra); // Remove the class attribute
            }
        }

        // Trim any extra spaces from $divClasses and $extra
        $divClasses = trim($divClasses);
        $extra = trim($extra);

        if($isFieldSetPage) {
            //do something here
        }
        $wrapperElem = !empty($parentAttr['isFieldset']) ? 'fieldset' : 'div';
        $wrapperLabel = !empty($parentAttr['isFieldset']) ? 'legend' : 'label';
        $cmsLabel = ($USER_TYPE == 'adminUser') ? cmsAdmin($label) : cms($label, 0);
        if(!empty($parentAttr['cmsParams'])) {
            $cmsLabel = sprintf($cmsLabel, ...$parentAttr['cmsParams']);
        }
        echo "<$wrapperElem $extra class=\"$divClasses\" ".($parentAttr['extra'] ?? '').">";
        echo '<'.$wrapperLabel.' '.( !empty($for) ? 'for="'.htmlspecialchars($for).'"' : '').'>'.$cmsLabel.'</'.$wrapperLabel.'>';

        $afterTitlehelpText = '';
        if(!empty($parentAttr['afterTitlehelpText'])) {
            $afterTitlehelpText = htmlspecialchars($parentAttr['afterTitlehelpText']);
        } else if(!empty($parentAttr['unsafe__afterTitlehelpText'])) {
            $afterTitlehelpText = $parentAttr['unsafe__afterTitlehelpText'];
        }

        if(!empty($afterTitlehelpText)) {
            $classes = ($USER_TYPE == 'adminUser') ? 'help-text' : 'info questionInfo form-text mb-0';
            echo '<div class="'.$classes.'">'.$afterTitlehelpText.'</div>';
        }

        if($parentAttr['isCheckBox']??false) {
            echo '<div class="checkbox-group">';
        }
        echo $ui;
        if($parentAttr['isCheckBox']??false) {
            echo '</div>';
        }
        echo $parentAttr['extraHtml']??'';
        
        $helpText = '';
        if(!empty($parentAttr['helpText'])) {
            $helpText = htmlspecialchars($parentAttr['helpText']);
        } else if(!empty($parentAttr['unsafe__helpText'])) {
            $helpText = $parentAttr['unsafe__helpText'];
        }

        if(!empty($helpText)) {
            $classes = ($USER_TYPE == 'adminUser') ? 'help-text mt-1' : 'info questionInfo form-text mb-0';
            echo '<div class="'.$classes.'">'.$helpText.'</div>';
        }
        echo "</$wrapperElem>";
    }
}

function formMultiSelect($name, $label='', $available=[], $selected = [], $extra = '', $checkBoxExtra = '', $checkBoxClass = '',$emptyMessage='', $showEmptyMessage = true) {

    global $USER_TYPE;
    
    $filterMultiSelect= 0;
    static $multiSelectFieldCount = 0;
    if(is_array($name)) {
        extract($name);
    }
    if(empty($filterMultiSelect)) {
        $filterMultiSelect = 0;
    }

    if($filterMultiSelect) {
        $checkBoxClass .= " ignoreChange ";        
    }

    $multiSelectFieldCount++;
    //Calculate list from an optionbox
    
    if ($available instanceof formOptionbox) {
        $options = $available->getOptions();
        if ($available->getAutoDefault() > 0) $available->generateDefault($available->getAutoDefault());
        $default = array_keys($available->getDefault());
        if(empty($label)) {
            $label = $available->getTitle();
        }
        $available = $options;
        $selected = $default;
    }

    if(empty($selected)) {
        $selected = [];
    }
    if(empty($available) && $showEmptyMessage) {
        $available = [];
        if(empty($emptyMessage)) {
            $emptyMessage = cms("No option available to select.",0);
        }
        echo '<div class="warning-message">'.$emptyMessage.'</div>';
    }

    static $includeMultiSelectJs = true;

    if($includeMultiSelectJs) {

        if($USER_TYPE != 'adminUser') { ?>
            <link rel="stylesheet" type="text/css" href="/adminDesignAssets/css/entrant.min.css?<?= cacheBuster(); ?>">
            <style>
                .multi-select__options li input[type="checkbox"].form-control {
                    appearance: auto !important;
                    -webkit-appearance: auto !important;
                }
            </style> 
        <? } ?>

        <script>
            var multiSelectSelectedRemoveIcon = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.56707 4.29498L9 3.86395L8.13605 3L7.70502 3.43102L6 5.13605L4.29498 3.43102L3.86395 3L3 3.86395L3.43102 4.29498L5.13605 6L3.43293 7.70502L3 8.13605L3.86395 9L4.29498 8.56898L6 6.86395L7.70502 8.56898L8.13605 9L9 8.13605L8.56898 7.70502L6.86395 6L8.56898 4.29498H8.56707Z" fill="#1463FF"></path></svg>';
        </script>
        <script src="/javascript/multiSelect.js?<?= cacheBuster(); ?>"></script>
        <?
        $includeMultiSelectJs = false;
    }

    $divDisplay = empty($available) ? 'display: none' : '';
    ?>
    <div class="multi-select<?=($filterMultiSelect)?"-th":"";?>" style="<?=$divDisplay?>">
    
        <? if($filterMultiSelect) { ?>
            <button type="button" class="multi-select-th__label ignoreChange" popovertarget="<?= $name ?>-field-<?= $multiSelectFieldCount; ?>">
                <span class="multi-select-th__label-text">Any</span>
                <span class="multi-select-th__label-icon">
                    <svg class="svg-icon svg-icon--arrow-down" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7.99988 12.3325L8.56488 11.7675L13.3649 6.96746L13.9324 6.39996L12.7999 5.26746L12.2349 5.83246L7.99988 10.0675L3.76488 5.83496L3.19988 5.26746L2.06738 6.39996L2.63238 6.96496L7.43238 11.765L7.99988 12.3325Z"></path>
                    </svg>
                </span>
            </button>
        <? } else { ?>
            <label class="multi-select__caret btn btn--icon" for="<?= $name ?>-field-<?= $multiSelectFieldCount; ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.99988 12.3325L8.56488 11.7675L13.3649 6.96746L13.9324 6.39996L12.7999 5.26746L12.2349 5.83246L7.99988 10.0675L3.76488 5.83496L3.19988 5.26746L2.06738 6.39996L2.63238 6.96496L7.43238 11.765L7.99988 12.3325Z" fill="#676F84"/></svg>
            </label>
            
            <input class="multi-select__is-open ignoreChange" type="checkbox" id="<?= $name; ?>-field-<?= $multiSelectFieldCount; ?>" />
            <label class="multi-select__label" for="<?= $name; ?>-field-<?= $multiSelectFieldCount; ?>"><?= $label; ?></label>
        <? } ?>
        <div class="multi-select<?=($filterMultiSelect)?"-th":"";?>__panel" <? if($filterMultiSelect){ ?> popover="" id="<?= $name ?>-field-<?= $multiSelectFieldCount; ?>" <? } ?>>
            <div class="multi-select<?=($filterMultiSelect)?"-th":"";?>__search">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path fill="#3F4B62" d="M9.8 5.8a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm-.773 4.077A5.2 5.2 0 0 1 .6 5.8 5.2 5.2 0 0 1 5.8.601a5.2 5.2 0 0 1 4.078 8.427l3.1 3.1.425.425-.85.848-.425-.425-3.1-3.1v.002Z"></path></svg>
                <input type="text" placeholder="Search">
            </div>
            <ul class="multi-select<?=($filterMultiSelect)?"-th":"";?>__options" <?= $extra; ?>>
                <? foreach($available AS $availableTitle => $availableValue) {
                    $checkBoxId = $name.'-opt-'.$availableValue;
                    $checkBoxId = str_replace(' ', '-', $checkBoxId);
                    $checkBoxId = preg_replace('/[^a-zA-Z0-9-]/', '', $checkBoxId);
                    ?>
                    <li>
                        <input <?= (in_array($availableValue, $selected??[]))?'checked':'' ?> name="<?= $name; ?>[]" type="checkbox" id="<?= $checkBoxId; ?>" value="<?= $availableValue; ?>" class="<?= $checkBoxClass; ?>">
                        <label for="<?= $checkBoxId; ?>"><?= $availableTitle; ?></label>
                    </li>
                <? } ?>
            </ul>
        </div>
        <? if(!$filterMultiSelect) { ?>
            <ul class="multi-select__selected"></ul>
        <? } ?>
    </div>
    <?
}

function formReadOnly($value, $extra = '') {
    if(strlen($value) > 70) {
        formTextarea( [ 
            'name' => uniqid(),
            'default' => $value,
            'extra' => $extra.' readonly disabled '
        ]);
    } else {
        formTextbox( [ 
            'name' => uniqid(),
            'default' => $value,
            'extra' => $extra.' readonly disabled '
        ]);
    }
}

/*
set the size to a negative number to indicate a PASSWORD box
You can either call this with formTextbox( $name, $size, $maxlength etc... );
OR you can pass an associative array of options as the second parameter e.g.
   formTextbox( $name, $options=[
      'size' => ...,
      'maxlength' => ...,
      etc...
    ])
*/
function formTextbox( $name, $sizeOrOptions=10, $maxlength='', $default=null, $extra='', $placeholder='' ) {
    if(is_array($name)) {
        extract($name);
    }

    if(is_array($sizeOrOptions)) {
        extract($sizeOrOptions);
        if(!isset($size)) {
            $size=10;
        }
    } else {
        $size = $sizeOrOptions;
    }
    
    $type = 'text';
    if ($size < 0) {
        $type = 'password';
        $size = $size * -1;
    }
    
    if(!empty($requiredClass)) {
        $extra = updateExtraFromAttribute($requiredClass, 'class', $extra);
    }
    formBox( $type, $name, $size, $maxlength, $default, $extra, $placeholder );
}

/*
This function can either be called with literal parameters => formDate($name, $minDate, $maxDate, etc...)
OR an array of options can be passed after the $name => formDate($name, $options)
OR an array of options can be passed instead of $name  => formDate( $options) // IN THIS CASE $options MUST CONTAIN AT LEAST ['name']
*/

function formDate($nameOrOptions, $minDateOrOptions = null, $maxDate = null, $size=10, $maxlength='', $default=null, $extra='',$type='date') {
    if(is_array($nameOrOptions)) {
        extract($nameOrOptions);
    } else {
        $name = $nameOrOptions;
        if(is_array($minDateOrOptions)) {
            // defaultFormField injects the name so we need the next parameter to be used as an array
            // if we dont want to default all parameters values.
            extract($minDateOrOptions);
        }
    }

    global $WS;
    $currentYear = date('Y');
    $minRangeYear = $currentYear - 100;
    $maxRangeYear = $currentYear + 100;
    $minDate = empty($minDate) ? 0 : $minDate;
    if (is_string($minDate) && !empty($minDate)) {
        if ($minDate === 'today') {
            $minDate = strtotime('today');
        } else {
            $minDate = dateTimeToTimestamp($minDate.' 00:00:00');
        }
    }
    $maxDate = is_null($maxDate) ? 0 : $maxDate;
    if (is_string($maxDate) && !empty($maxDate)) {
        if ($maxDate === 'today') {
            $maxDate = strtotime('today');
        } else {
            $maxDate = dateTimeToTimestamp($maxDate.' 00:00:00');
        }
    }

    if (is_string($default) && !empty($default)) {
        if ($default === 'today') {
            $default = strtotime('today');
        } else {
            $default = dateTimeToTimestamp($default.' 00:00:00');
        }
        if($type == "datetime-local" && !empty($default) && is_numeric($default)) {
            $default = date('Y-m-d\TH:i', (int)$default);
        } else {
            $default = [ date('Y-m-d', intval($default)) ];
        }
    }

    $minYear = intval($minDate) > 0 ? date('Y-m-d', intval($minDate)) : $minRangeYear.date('-m-d');
    $maxYear = intval($maxDate) > 0 ? date('Y-m-d', intval($maxDate)) : $maxRangeYear.date('-m-d');
    $extra.= " min=\"{$minYear}\" max=\"{$maxYear}\" ";

    if(isset($WS[$name]) && empty($WS[$name])) {
        $WS[$name] = '';
    }
    if (empty($default) && isset($WS[$name])) {
        if(is_numeric($WS[$name])) {
            //timestamp
            $default = (int)$WS[$name];
            if($type == "datetime-local") {
                $default = date('Y-m-d\TH:i', (int)$default);
            } else {
                $default = [ date('Y-m-d', intval($default)) ];
            }
        } else {
            // Attempt 1
            $date1 = DateTime::createFromFormat('d/m/Y', $WS[$name]);
            if ($date1 !== false) {
                $default = [ $date1->format('Y-m-d') ];
            } else {
                // Attempt 2
                $date2 = DateTime::createFromFormat('Y-m-d', $WS[$name]);
                if ($date2 !== false) {
                    $default = [ $date2->format('Y-m-d') ];
                }
            }
        }
    }
    if(empty($default)) {
        $extra = updateExtraFromAttribute('placeholder-hidden', 'class', $extra);
    }
    $extra .= ' onfocus="this.showPicker()" ';
    formBox( $type,$name,10,10,$default,$extra);
}

function formTime($name, $size=10, $maxlength='', $default=null, $extra='') {
    if (!preg_match('/step="/', $extra)) { $extra .= ' step="300" '; }
    $extra .= ' onfocus="this.showPicker()" ';
    formBox( 'time',$name,10,10,$default,$extra);
}

function formEmail( $name, $size=10, $maxlength='', $default=null, $extra='' ) {
    formBox( 'email', $name, $size, $maxlength, $default, $extra );
}

function formPhone( $name, $size=10, $maxlength='', $default=null, $extra='' ) {
    global $print;
    static $includeJsCss = true;
    if($print) {
        $includeJsCss = false;
    }

    formBox( 'tel', $name, $size, $maxlength, $default, $extra );

    if($includeJsCss) {
        ?>
        <script src="/javascript/intl-tel-input/js/intlTelInput-jquery.min.js?<?= cacheBuster(); ?>"></script>
        <link rel="stylesheet" href="/javascript/intl-tel-input/css/intlTelInput.min.css?<?= cacheBuster(); ?>">
        <script>
            $(function () {
                if($().intlTelInput) {
                    $('input[type=tel]').closest('form').on('submit', function (ev) {
                        $(this).find('input[type=tel]').each(function () {
                            var phoneInput = $(this);

                            phoneInput.val(phoneInput.intlTelInput('getNumber'));
                        });
                    });
                }
            });
        </script>
        <?
        $includeJsCss = false;
    }
}

function formNumber($name, $size=10, $min=null, $max=null, $step=1, $default=null, $extra='') {
    if(is_numeric($min)) {
        $extra .= ' min="'.((int)$min).'"';
    }
    if(is_numeric($max)) {
        $extra .= ' max="'.((int)$max).'"';
    }
    if(is_numeric($step)) {
        $extra .= ' step="'.((int)$step).'"';
    }
    formBox('number', $name, $size, '', $default, $extra);
}

function formBox( $type, $name, $size=10, $maxlength='', $default=null, $extra='', $placeholder='' ) {
    global $WS;
    if (is_array($default)) {
        $default = $default[0];
    }
    if (isset( $WS[$name] ) && trim($WS[$name]) !== "") {
        $default = $WS[$name];
    }
    if($type == "datetime-local" && !empty($default) && is_numeric($default)) {
        $default = date('Y-m-d\TH:i', (int)$default);
    }

    echo '<INPUT TYPE="'.$type.'" NAME="'.htmlspecialchars($name).'" SIZE="'.$size.'" ';
    if ($maxlength<>'') echo 'MAXLENGTH="'.$maxlength.'" ';
    if(!empty($placeholder)) {
        echo ' placeholder="'.$placeholder.'" ';
    }
    echo 'VALUE="'.htmlspecialchars($default).'" '.$extra.' />';
}

function formFile( $name, $size=10, $allowedFileType='', $default=null, $extra='' ) {
    if(is_array($name)) {
        extract($name);
    }
    global $WS,$USER_TYPE,$loadFileFieldJs,$formFileStyle;
    $formFileStyle = $formFileStyle??'';
    if (is_array($default)) $default = $default[0];
    else if (isset( $_FILES[$name] )) $default = $_FILES[$name]['name'];

    if($USER_TYPE == 'adminUser' || $formFileStyle == 'adminUser') {
        $extra .= ' style="display:none" ';
        static $includeFieldFileJs = true;
        $loadFileFieldJs = $loadFileFieldJs??true;
        if(!$loadFileFieldJs) {
            $includeFieldFileJs = false; 
        }
        if($includeFieldFileJs) {
            echo '<script src="/javascript/fieldFile.js?'.cacheBuster().'"></script>';
            $includeFieldFileJs = false;
        }
    ?>
        <div class="file-upload">
            <div class="file-upload__drop-area">
                <div class="file-upload__drop-icon">
                    <?= loadSVGIcon('uploadFileIcon'); ?>
                </div>
                <p><a href="#" class="btnSelectToUploadFile">Click to upload</a></p>
            </div>
            <div class="fileUploadSelectedFile" style="display:none">
                <br>
                <strong>Selected File</strong>
                <div class="file-upload__files">
                    <div class="file-upload__file">
                        <div class="file-upload__file__status">
                            <?= loadSVGIcon('uploadFileSelected'); ?>
                        </div>
                        <strong class="file-upload__file__name"></strong>
                        <div class="file-upload__file__size"><span></span></div>
                        <button class="file-upload__file__delete">
                            <?= loadSVGIcon('delete'); ?>
                        </button>
                    </div>
                </div>
            </div>
    <? }
    echo '<INPUT TYPE="file" NAME="'.htmlspecialchars($name).'" SIZE="'.$size.'" ';
    if ($allowedFileType<>'') echo 'ACCEPT="'.$allowedFileType.'" ';
    echo 'VALUE="'.htmlspecialchars($default).'" '.$extra.' />';
    if($USER_TYPE == 'adminUser') {
        echo '</div>';
    }
}

/*
This function can either be caled with literal paramaters => formTextarea( $name, $cols, $rows="3", etc... )
OR it can be called with name and an options array => formTextarea( $name, $options )
OR it can be called with just an options array => formTextarea( $options ) // IN THIS CASE $options MUST CONTAIN AT LEAST ['name']
*/
function formTextarea( $nameOrOptions, $colsOrOptions="80", $rows="3", $default = null, $extra = '' ) {
    $cols = 80;
    if(is_array($nameOrOptions)) {
        extract($nameOrOptions);
    } else {
        $name = $nameOrOptions;
        if(is_array($colsOrOptions)) {
            extract($colsOrOptions);
        } else {
            $cols = $colsOrOptions;
        }
    }
    
    global $WS;
    echo "<textarea rows=\"$rows\" cols=\"$cols\" name=\"".htmlspecialchars($name).'" '.$extra.' >';
    if ($default == null) $default = isset($WS[$name])?$WS[$name]:'';
    echo htmlspecialchars($default);
    echo '</textarea>';
}

function formSubmit($name, $value='', $displayName=null, $extra='', $print = true, $icon='', $extraClass='', $buttonType='', $buttonStyle='', $class='') {
    global $USER_TYPE;
    if(is_array($name)) {
        foreach($name as $key => $keyValue) {
            $$key = $keyValue;
        }
    }
    if (is_null($displayName)) $displayName = cms($value,0);
    
    if($USER_TYPE == 'adminUser') {
        if(empty($buttonStyle)) {
            $buttonStyle = 'btn--tertiary';
        }
        if(empty($buttonClass)) {
            $buttonClass = "btn $buttonStyle $extraClass";
        }
        $extra = ' class="'.$buttonClass.'" '.$extra;
        $displayName = '<span>'.$displayName.'</span>';
        if(!empty($icon)) {
            $displayName .= loadSVGIcon($icon);
        }
    }
    if(empty($buttonType)) {
        $buttonType = "submit";
    }
    
    $return = "<button $extra name=\"$name\" type=\"$buttonType\" value=\"".htmlspecialchars($value)."\">$displayName</button>";
    if($USER_TYPE == 'adminUser') {
        $return .= "\n";
    }
    if($print) { echo $return; }
    return $return;
}

/*
 * https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
 */
function isAssociativeArray(array $arr) {
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/*
 * Two-list selector
 * $availableList & $selectedList should either be a formOptionbox or sql to create one
 * $selectedList can also be an array of ID's - in this case the display values will be looked up from $availableList
 *     and the corresponding values will be removed from $availableList
 * OR pass a single query as $availableList returning 'option', 'value', 'selected' to populate both boxes
 *    NB `option` may have to be backtick-quoted as it's a reserved word in some mysql versions
 * $labels can contain 'availableTitle', 'selectedTitle' and 'explanation', empty string to be blank, unset to use default
 */
function formPicklist($name, $availableList, $selectedList, $labels = array()) {
    global $DB;
    static $doJS = true;

    if(substr($name, -2) == '[]') {
        // formOptionbox will add the "[]" back on
        // but it needs to be in the view for deriveInputsFromFile() to work out it's an array
        $name = substr($name, 0, -2);
    }

    if(!isset($labels['availableTitle'])) $labels['availableTitle'] = cms('Available');
    if(!isset($labels['selectedTitle'])) $labels['selectedTitle'] = cms('Selected');
    if(!isset($labels['explanation'])) $labels['explanation'] = cms('Click an item to move it to the other list').'<br />'.cms('Click and drag to move multiple items').'<br />'.cms('Click in the box and press Ctrl-a to move all items');

    if($selectedList !== null) {
        if(!is_object($availableList)) {
            $availableList = new formOptionbox('_', $availableList);
        }

        if(!is_object($selectedList)) {
            if(!isAssociativeArray($selectedList)) {
                $selectedList = array_intersect($availableList->options, $selectedList);
                $availableList->options = array_diff($availableList->options, $selectedList);
            }
            $selectedList = new formOptionbox('_', $selectedList);
        }
    } else {
        $availableOptions = $selectedOptions = array();
        $allOptions = $DB->getRows($availableList);
        foreach($allOptions as $option) {
            if($option['selected']) $selectedOptions[$option['option']] = $option['value'];
            else $availableOptions[$option['option']] = $option['value'];
        }
        $availableList = new formOptionbox('_', $availableOptions);
        $selectedList = new formOptionbox('_', $selectedOptions);
    }

    $availableList->setName($name.'_available');
    $availableList->setMultiple(true);
    $availableList->setDefault(null);

    $extraClasses = '';
    $extraAttributes = parseHtmlAttributes($availableList->extra, true);
    if(isset($extraAttributes['class'])) {
        $extraClasses = $extraAttributes['class'];
        unset($extraAttributes['class']);
    }
    $extraAttributes = htmlAttributeString($extraAttributes);
    $availableList->setExtra($extraAttributes.' class="picklistList picklistAvailable '.$extraClasses.'" id="picklist_'.$name.'_available" data-picklist-pair="#picklist_'.$name.'_selected"');


    $selectedList->setName($name);
    $selectedList->setMultiple(true);
    $selectedList->autoDefault(false);
    

    $extraClasses = '';
    $extraAttributes = parseHtmlAttributes($selectedList->extra, true);
    if(isset($extraAttributes['class'])) {
        $extraClasses = $extraAttributes['class'];
        unset($extraAttributes['class']);
    }
    $extraAttributes = htmlAttributeString($extraAttributes);
    $selectedList->setExtra($extraAttributes.' class="picklistList picklistSelected '.$extraClasses.'" id="picklist_'.$name.'_selected" data-picklist-pair="#picklist_'.$name.'_available"');
    ?>
    <div class="picklistHolder">
        <div class="picklistListHolder picklistAvailable">
            <?
            if($labels['availableTitle']) {
                echo '<div class="picklistListTitle">'.$labels['availableTitle'].'</div>';
            }
            $availableList->display();
            ?>
        </div>
        <div class="picklistListHolder picklistSelected">
            <?
            if($labels['selectedTitle']) {
                echo '<div class="picklistListTitle">'.$labels['selectedTitle'].'</div>';
            }
            $selectedList->display();
            ?>
        </div>
        <?
        if($labels['explanation']) {
            echo '<div class="picklistExplanation">'.$labels['explanation'].'</div>';
        }

        if($doJS) { ?>
            <script>
                $(function () {
                    $('.picklistList').on('change', function (ev, fromHere) {
                        if(fromHere) return;
                        var moveOption = $(ev.target).parent().find(':selected'),
                            moveToList = $($(ev.delegateTarget).data('picklistPair').replace(/[\[\]]/g, '\\$&'));

                        var moveToListMaxOptions = parseInt(moveToList.attr('max'));
                        var moveToListNumOptions = moveToList.find('option').length;
                        var isAvailablePickList = $(this).parent().hasClass('picklistAvailable');
                        if(isAvailablePickList && moveToListMaxOptions > 0 && moveToListMaxOptions <= moveToListNumOptions) {
                            return false;
                        }
                        moveOption.remove().appendTo(moveToList);
                        moveToList.find('option:selected').prop('selected', false);
                        // change event doesn't fire on the moveToList which would prevent the entry form auto-validate
                        moveToList.trigger('change', [ true ]);
                        // if we're a dependentInputs dependee fire another change event now the option's been removed
                        var fromList = $(this);
                        if(fromList.hasClass('isDependee')) {
                            fromList.trigger('change', [ true ]);
                        }
                    });

                    $('.picklistHolder').closest('form').on('submit', function () {
                        $(this).find('select.picklistSelected option').prop('selected', true);
                    });

                });
            </script>
            <?
            $doJS = false;
        } ?>
    </div>
    <?
}

function formMultiHidden($regexp, $hash='') {
    global $WS;
    if (!is_array($hash)) $hash = $WS;
    $fields = fuzzyLookup( $hash, $regexp );
    foreach($fields as $key => $value) {
        formHidden( $key, $value );
    }
}

function formPlaintext( $notUsed, $text) {
    echo '<div>'.$text.'</div>';
}

function formPlaceholder( $notUsed ) {
    // this function is intetionally empty
}

/*
This function creates a HIDDEN input field
    If called with just one parameter it uses this as the NAME and looks for the value of the field with the same name in the workspace if this field is not set then the value is defaulted to ''
    If called with two parameters then these are taken to be the NAME and VALUE and no reference is made to the Workspace
    If called with three parameters then the first is taken to be the NAME, the second a workspace field to try and obtain the VALUE from (if this is empty then the value from the first parameter is used) and the third a default VALUE to us if the specified workspace field is not set
*/
function formHidden( $name, $arg1=null, $arg2=null, $arg3=null) {
    global $WS;
    $extra = '';
    if (is_array($name)) {
        $options = $name;
        $name = $options['name'];
        $extra = isset($options['extra']) ? $options['extra']:'';
        $value = isset($options['value']) ?    $options['value'] : ( isset($options['default']) ? $options['default'] : (     isset($WS[$name]) ?    $WS[$name] : ''    ) );
    } else {
        if ($arg3!==null) {
            // 4 argument call
            // name, workspace_field, default_value, $extra
            $extra = $arg3;
        }
        if ($arg2!==null) {
            // 3 argument form call
            // name, workspace_field, default_value
            if ($arg1 == '') $arg1 = $name;
            if (isset($WS[$arg1])) {
                $value = $WS[$arg1];
            } else {
                $value = $arg2;
            }
        } else if ($arg1!==null) {
            // 2 argument form call
            // name, value
            $value = $arg1;
        } else {
            // 1 argument form
            // name
            if (isset($WS[$name])) {
                $value = $WS[$name];
            } else {
                $value = '';
            }
        }
    }
    if (is_array($value)) {
        $idx=0;
        $useKey=false;
        foreach($value as $key => $content) {
            if (((int)$key)!=$idx) $useKey=true;
            if (!$useKey) $key='';
            $arrayName = $name.'['.$key.']';
            if (is_array($content)) formHidden($arrayName,$content);
            else echo '<INPUT '.$extra.' TYPE="HIDDEN" NAME="'.htmlspecialchars($arrayName).'" VALUE="'.htmlspecialchars($content).'" />';
            $idx++;
        }
    } else {
        echo '<INPUT '.$extra.' TYPE="HIDDEN" NAME="'.htmlspecialchars($name).'" VALUE="'.htmlspecialchars($value).'" />';
    }
}

function formCheckbox( $name, $value=null, $extra=null, $label=null, $extraGroup = '' ) {
    global $USER_TYPE;
    if(is_array($name)) {
        extract($name);
    }
    global $WS;
    if ($value==null) $value = 1;

    $id = '';
    if ($label!==null) {
        if($USER_TYPE == 'adminUser') {
            echo '<div '.$extraGroup.' class="checkbox-group"><div>';
        } else {
            echo '<span '.$extraGroup.' class="checkbox">';
        }
        static $idCount;
        if (!isset($idCount)) $idCount=1;
        $id = htmlspecialchars($name).'_'.$idCount++;
    }
    echo '<input ';
    if ($id) echo 'id="'.$id.'" ';
    if ($extra) echo $extra;
    echo ' name="'.htmlspecialchars($name).'" type="checkbox" value="'.htmlspecialchars($value).'"';
    if (isset($WS[$name]) && $WS[$name]==$value) echo 'checked="yes"';
    echo ' />';
    if ($label!==null) {
        echo '<label for="'.$id.'">'.htmlspecialchars($label).'</label>';
    }
    if ($label!==null) {
        if($USER_TYPE == 'adminUser') {
            echo '</div></div>';
        } else {
            echo '<br/></span>';
        }
    }
}

/*
$selector:
    #whateverId - all the checks in container #whateverId
    .whateverClass - all the in containers with class whateverClass or checks having class whateverClass
    whateverName - all checks having name whateverName
*/
function selectAllNoneChecks($selector, $labels = array(), $visibleOnly = false, $doJSOverride = null) {
    static $doJS = true;

    if(!isset($labels['prefix'])) $labels['prefix'] = cms('Select', 0);
    if(!isset($labels['allLink'])) $labels['allLink'] = cms('All', 0);
    if(!isset($labels['noneLink'])) $labels['noneLink'] = cms('None', 0);
    if(!isset($labels['resetLink'])) $labels['resetLink'] = cms('Reset', 0);

    ?>
    <div class="allNoneChecks" data-check-selector="<?=htmlspecialchars($selector)?>" data-visible-only="<?=(int)(bool)$visibleOnly?>">
        <?= $labels['prefix'] ?: '' ?>
        <a href="#" class="allNoneChecksLink" data-checks-checked="1"><?=$labels['allLink']?></a>
        <? if($labels['noneLink']) { ?>
            | <a href="#" class="allNoneChecksLink" data-checks-checked="0"><?=$labels['noneLink']?></a>
        <? }
        if($labels['resetLink']) { ?>
            | <a href="#" class="allNoneChecksLink" data-checks-checked="-"><?=$labels['resetLink']?></a>
        <? } ?>
    </div>
    <?

    global $USER_TYPE;
    if($doJS && (!is_bool($doJSOverride) || $doJSOverride)) { ?>
        <script>
            $(function () {
                var isAdminSite ="<?= ($USER_TYPE == 'adminUser')?1:0; ?>";
                $('body').on('click', '.allNoneChecksLink', function (ev) {
                    var link = $(ev.target),
                        parentDiv = link.closest('div.allNoneChecks'),
                        newChecked = parseInt(link.attr('data-checks-checked')),
                        selector = parentDiv.attr('data-check-selector'),
                        visibilityCheck = parentDiv.attr('data-visible-only') ? ':visible' : '';

                    if(isAdminSite) {
                        selector = link.closest('div.checkbox-group');
                    } else {
                        if(!(selector.substr(0, 1) === '.' || selector.substr(0, 1) === '#')) {
                            selector = 'input:checkbox[name="' + selector + '"]';
                        }
                        selector += visibilityCheck;
                    }

                    $(selector).add($(selector).find('input:checkbox'+visibilityCheck)).each(function() {
                        var elem = $(this);
                        elem.prop('checked', !isNaN(newChecked) ? newChecked : Boolean(elem.attr('checked')));
                        elem.trigger('change');
                    });

                    return false;
                });
            });
        </script>
        <?
        $doJS = false;
    }
}

function formRadio( $name, $value=null, $default=null, $extra='' ) {
    if(is_array($name)) {
        extract($name);
    }
    global $WS;
    if ($value==null) $value = 1;
    if ($default==null) $default = isset($WS[$name])?$WS[$name]:'';
    echo '<input '.$extra.' name="'.htmlspecialchars($name).'" type="radio" value="'.htmlspecialchars($value).'"';
    if ($default==$value) echo 'checked="yes"';
    echo ' />';
}

function formOptionbox($name, $options, $extra = '',$attr = []) {
    // Check if $options is already an instance of formOptionbox
    if (!$options instanceof formOptionbox) {
        $optionBox = new formOptionbox($name, $options);
    } else {
        $optionBox = $options;
    }
    if(!empty($attr["setDefault"])) {
        $optionBox->setDefault($attr["setDefault"]);
        $optionBox->setMultiple();
    }
    if($attr["setMultiple"]??false) {
        $optionBox->setMultiple();
    }
    if($attr["type"]??'' == "checkbox") {
        echo '<div class="checkbox-group">';
        $optionBox->displayCheckboxes();
        echo '</div>';
    } else if($attr["redisplay"]??false) {
        $optionBox->redisplay($name);
    } else {
        $optionBox->display($extra);
    }
}

function formOptionCheckboxes($name, $options, $extra = '', $selectAllNone = false) {
    // Check if $options is already an instance of formOptionbox
    if (!$options instanceof formOptionbox) {
        $optionBox = new formOptionbox($name, $options);
    } else {
        $optionBox = $options;
    }
    echo '<div class="checkbox-group">';
    if($selectAllNone) {
        selectAllNoneChecks($name);
    }
    $optionBox->displayCheckboxes($extra);
    echo '</div>';
}

function formSubHeader($title, $extra = '') {
    global $USER_TYPE;

    if($USER_TYPE == 'adminUser') {
        echo "<br><br>";
        echo'<h2 class="f-title-md" '.$extra.'>'.cmsAdmin($title).'</h2>';
        echo "<br>";
    } else {
        echo "<br>";
        echo'<h3 '.$extra.'>'.cms($title, 0).'</h3>';
        echo "<br>";
    }
}

function formDisplay($value, $extra = '') {
    global $USER_TYPE;
    echo "<div $extra>";
    echo htmlspecialchars($value);
    echo "</div>";
}

function formUnsafe__Display($value) {
    global $USER_TYPE;
    echo "<div>";
    echo $value;
    echo "</div>";
}

// Just a wrapper for yesNo() so it can be called by formLabel() / defaultFormField()
function formYesNo($name, $filterVal='-- all --', $reverse=false, $extra='') {
    if(is_array($name)) {
        extract($name);
    }
    yesNo($name, $filterVal, $reverse, $extra);
}

function formTypeToSearch($fieldName, $url = '', $searchFieldExtraClass='', $searchFieldExtraAttr='', $noResultsFoundMessage='No results') {
    $size = 20;
    $default = '';
    $hiddenExtra = '';
    if(is_array($fieldName)) {
        $options = $fieldName;
        $fieldName = isset($options['fieldName']) ? $options['fieldName'] : '';
        $url = isset($options['url']) ? $options['url'] : '';
        if(isset($options['size'])) $size = $options['size'];
        if(isset($options['default'])) $default = $options['default'];
        if(isset($options['hiddenExtra'])) $hiddenExtra = $options['hiddenExtra'];
    }

    static $nextId = 0;
    ?>
    <div class="tts-holder" id="tts-<?= $nextId ?>">
        <div class="tts-click-cover" id="ttsClickCover-tts-<?=$nextId?>" tts-id="tts-<?= $nextId ?>" style="display: none; position: fixed; top: 0; right: 0; left: 0; height: 100vh"></div>
        <input <?= $searchFieldExtraAttr; ?> type="text" placeholder="<?= $options['placeholder']??''; ?>" class="tts-search <?= $searchFieldExtraClass; ?>" tts-id="tts-<?= $nextId ?>" tts-url="<?= htmlspecialchars($url) ?>" size="<?= $size ?>">
        <div class="tts-results-holder">
            <div class="tts-results-wrapper">
                <ul class="tts-results-list"></ul>
            </div>
        </div>
        <input type="hidden" name="<?= $fieldName ?>" value="<?= $default ?>" class="tts-value" <?=$hiddenExtra?>>
    </div>
    <?
    if($nextId == 0) {
        ?>
        <style>
            .tts-click-cover {
                z-index: 1;
            }

            .tts-holder {
                position: relative;
            }

            .tts-holder .tts-search {
                z-index: 2;
                position: relative;
            }

            .tts-holder .tts-results-holder {
                z-index: 3;
                display: none;
                position: absolute;
                width: 100%;
                background: #fff;
                border: 1px solid #999;
                border-top: 0px;
                border-radius: 5px;
            }

            .tts-holder .tts-results-wrapper {
                overflow-y: scroll;
            }

            .tts-holder .tts-results-list li {
                cursor: pointer;
                white-space: nowrap;
            }
        </style>
        <script>
            $(function () {
                const MIN_SEARCH_CHARS = 3;
                let ttsCurrentSearch = null;
                let ttsBuff = {};

                function showTTSSearchResults(ttsId, results) {
                    let ttsResultsList = $('#' + ttsId).find('.tts-results-list');
                    let newLIs = [];

                    for(let resIdx in results) {
                        let newLI = $('<li tts-value="' + results[resIdx].value + '"></li>');
                        newLI.text(results[resIdx].item);
                        newLIs.push(newLI);
                    }

                    if(!newLIs.length) {
                        newLIs.push($('<li tts-value=""><?=$noResultsFoundMessage;?></li>'));
                        if($('#' + ttsId).find('.tts-search').hasClass("dontResetAfterSearch")) {
                            $('#' + ttsId).find('.tts-value').val("0").trigger("change");
                        }
                    }

                    ttsResultsList.empty().append(newLIs);
                    showTTSSearchResultsList(ttsId);
                }

                function showTTSSearchResultsList(ttsId) {
                    $(`#ttsClickCover-${ttsId}`).show();
                    $('#' + ttsId).find('.tts-results-holder').show();
                }

                function hideTTSSearchResultsList() {
                    $('.tts-click-cover').hide();
                    $('.tts-results-holder').hide();
                }

                function swapTTSText(ttsId) {
                    let ttsSearch = $('#' + ttsId).find('.tts-search');
                    if(!ttsSearch.hasClass("dontResetAfterSearch")) {
                        let oldText = ttsSearch.val();
                        ttsSearch.val(ttsBuff[ttsId]);
                        ttsBuff[ttsId] = oldText;
                    }
                }

                $('.tts-click-cover').on('click', function () {
                    let ttsId = $(this).attr('tts-id')

                    swapTTSText(ttsId);

                    hideTTSSearchResultsList();
                });

                $('.tts-search').on('focus', function () {
                    let ttsSearch = $(this);
                    let ttsId = ttsSearch.attr('tts-id');
                    let ttsResultCount = $('#' + ttsId).find('.tts-results-list li').length;

                    swapTTSText(ttsId);

                    if(ttsSearch.val().length >= MIN_SEARCH_CHARS && ttsResultCount) {
                        showTTSSearchResultsList(ttsId);
                    }
                });

                $('.tts-search').on('keyup', function (ev) {
                    let ttsSearch = $(this);
                    let ttsId = ttsSearch.attr('tts-id');
                    let searchVal = ttsSearch.val();

                    if(ev.which === 27) { // Esc
                        // catching onBlur has issues when choosing a result so this is easier
                        $(`#ttsClickCover-${ttsId}`).trigger('click');
                        return;
                    }

                    if(searchVal.length >= MIN_SEARCH_CHARS) {
                        if(ttsCurrentSearch !== null) {
                            ttsCurrentSearch.abort('Search cancelled');
                        }

                        ttsCurrentSearch = $.ajax({
                            url: ttsSearch.attr('tts-url') + searchVal,
                            dataType: 'json',
                            success: function (data) {
                                showTTSSearchResults(ttsSearch.attr('tts-id'), data);
                            },
                            error: function (_jqXHR, _textStatus, _error) {
                                console.log(_jqXHR);
                                console.log(_textStatus);
                                console.log(_error);
                            }
                        });
                    } else {
                        hideTTSSearchResultsList();
                    }
                });

                $('ul.tts-results-list').on('click', 'li', function () {
                    let selectedItem = $(this);
                    let ttsValue = selectedItem.attr('tts-value');
                    if(ttsValue === '') return;

                    let ttsHolder = selectedItem.closest('.tts-holder');
                    let ttsSearch = ttsHolder.find('.tts-search');

                    ttsHolder.find('.tts-value').val(ttsValue).trigger('change');

                    // Save the current search value
                    swapTTSText(ttsHolder.attr('id'));
                    ttsSearch.val(selectedItem.text());

                    hideTTSSearchResultsList();
                });
            });
        </script>
        <?
    }
    $nextId++;
}

// ============================================================================
// ============================== OPTIONBOX ===================================
// ============================================================================

/* Some examples of usage

$ob = new formOptionbox( 'cgi_name' );
$ob = new formOptionbox( 'cgi_name', 'SELECT sql STATEMENT', '', default ); // this will use default db ($DB)
$ob = new formOptionbox( 'cgi_name', 'SELECT sql STATEMENT', $db, default );
$ob = new formOptionbox( 'cgi_name', array('option1' => value1, 'option2' => value2), [default] );
$ob->addOption('name','value');
$ob->addOption( array('option1' => value1, 'option2' => value2) );
$ob->addOption( array('option1', 'option2'), 1 ); // 1 for second argument indicates "flat" array i.e. not hash - so value==key for each option
$ob->addLookup('SELECT sql STATEMENT', [$db]); // if no database set here or when object created then this will use default db ($DB)
$ob->removeOption('value');
$ob->removeOption( array('value1', 'value2', 'value3') );
$ob->removeLookup('SELECT values_to_remove FROM lookup', [$db]);
$ob->defaultLookup('SELECT values_to_default FROM lookup', [$db]);
$ob->setDefault( 'default value' );
$ob->setDefault( array('selected','also selected','another selected value') );
$ob->addDefault( 'another default value' );
$ob->addDefaults( array('selected','also selected','another selected value') );
$ob->addDefaults( regexp, 'value'|'option' ); // ads all options for which the option/value (as specified) matches the regexp
$ob->setExtra( 'onChange="extra stuff to go in SELECT tag"' );
$ob->setName( 'new_name' );
$ob->setRows( 'rows' );
$ob->setMultiple( 'multiple'|'single'|1|0 );
$ob->setDb( $database_handle );
$ob->display();
$ob->showDefault()

*/

    class formOptionbox {

        var $dbh = '';
        var $autoDefault = 1;
        var $default = array();
        var $options = array();
        var $name;
        var $rows = '';
        var $extra = '';
        var $multiple = 0;
        var $rawOutput = 0;
        var $autoHide = FALSE;
        var $disabled = 0;
        var $isHidden = FALSE;
        var $afterLabelCallback = null;
        var $afterLabelContentIfSelected = null;
        var $extraOptionAttributes = array();
        var $useCmsInLookup = false;

        //Constructor
        //  $ob = new optionbox( 'chooser', ['multiple'|'single'|1|0, display_rows] );
        //  $ob = new optionbox( 'chooser', 'SELECT sql STATEMENT', '', default, ['multiple'|'single'|1|0, display_rows] );
        //  $ob = new optionbox( 'chooser', 'SELECT sql STATEMENT', $db, default, ['multiple'|'single'|1|0, display_rows] );
        //  $ob = new optionbox( 'chooser', array('option1' => value1, 'option2' => value2), default, ['multiple'|'single'|1|0, display_rows] );
        function __construct( $name, $arg1 = '', $arg2 = null, $arg3 = null, $arg4 = '', $arg5 = '' ) {
            global $DB;
            $this->dbh = $DB;
            $this->name = $name;
            if ( is_array($arg1) ) {
                $this->addOption( $arg1 );
                if ($arg2 <> null) $this->setDefault( $arg2 );
            } else {
                if ( $arg1 <> '' && $arg1 <> 'multiple' && $arg1 <> 'single' && $arg1 <> 'MULTIPLE' && $arg1 <> 'SINGLE' && $arg1 <> '1' && $arg1 <> '0' ) {
                    if ( $arg2 == null ) { $this->dbh = $DB; }
                    else { $this->dbh = $arg2; }
                    $this->addLookup( $arg1 );
                    if ($arg3 <> null) $this->setDefault( $arg3 );
                    if ($arg4 <> '') { $this->setMultiple($arg4); }
                    if ($arg5 <> '') { $this->setRows($arg5); }
                } else {
                    if ($arg1 <> '') $this->setMultiple($arg1);
                    if ($arg2 <> null) $this->setRows($arg2);
                }
            }
        }

        function enableCmsInLookUpOptions() {
            $this->useCmsInLookup = true;
        }

        function addLookup( ) {
            global $DB;
            if ($this->dbh <> '') { $db = $this->dbh; }
            else { $db = $DB; }

            $data = $db->getHash(...func_get_args());

            foreach($data as $key => $value) {
                $this->options[($this->useCmsInLookup ? cms($key,0) : $key)]= $value;
            }

        }

        function addEnum( $table, $column, $formatter=null) {
            global $DB;
            if (!is_callable($formatter)) $formatter='ucfirst';
            if ($this->dbh <> '') { $db = $this->dbh; }
            else { $db = $DB; }

            $toAdd = $db->getEnumValues($table, $column);
            $newToAdd=array();
            foreach ($toAdd as $key=>$value) {
                $key = $formatter($key);
                if ($key!==null) $newToAdd[$key] = $value;
            }
            $toAdd = $newToAdd;

            $this->addOption($toAdd);
        }

        function setMultiple( $param = '1' ) {
            if ($param === 'single' || $param === 'SINGLE' || !$param) {
                $this->multiple = 0;
            } else {
                $this->multiple = 1;
                if (preg_match('/^[0-9]*$/', $param)) $this->rows = $param;
            }
        }
        function setExtraOptionAttributes($param = array())
        {
            $this->extraOptionAttributes = $param;
        }

        function numRows() {
            return count( $this->options );
        }

        function setRows( $rows ) {
            $this->rows = $rows;
            return $rows;
        }

        function addOption( $arg1, $arg2 = null, $position=null) {
            if ( is_array($arg1) ) {
                if ($arg2 == null || !$arg2) { $arg2 = 0; }
                reset ($arg1);
                foreach($arg1 as $key => $value) {
                    $this->options[$arg2?$value:$key] = $value;
                }
            } else {
                if ( $arg2 === null ) {
//                    echo "---$arg2---";
                    $arg2 = $arg1;
                }
                if (is_null($position)) {
                    $this->options[$arg1] = $arg2;
                } else {
                    $this->options =
                        array_slice($this->options, 0, $position, true) +
                        array($arg1 => $arg2) +
                        array_slice($this->options, $position, count($this->options)-$position, true)
                    ;
                }
            }
        }

        // $which idicates whether the removal is based on key or value
        //    i.e. is $arg1 a list of option values => 0 or a list of option names => 1 to be removed
        //    default is true => values
        // $test defines how the keys are tested
        //    0 -> exact comparison
        //    1 -> case insensitive comparison (default)
        //    2 -> case sensitive regular expression
        //    3 -> case insensitive regular expression
        function removeOption( $arg1, $which = 0, $test = 1 ) {
            if ( !is_array($arg1) ) { $arg1 = array( $arg1 ); }
            pruneHash( $this->options, $arg1, $which, 1, $test );
        }

        function removeLookup( $sql, $db='') {
            if ($db == '') {
                global $DB;
                if ($this->dbh <> '') { $db = $this->dbh; }
                else { $db = $DB; }
            }
            $this->removeOption( $db->getColumn($sql) );
        }

        function defaultLookup( $sql, $db='') {
            if ( $db=='' ) $db = $this->dbh;
            $newDefaults = $db->getColumn($sql);
            if (!count($newDefaults)) $newDefaults = array( null );
            $this->setDefault( $newDefaults );
        }

        function generateDefault( $rows = '' ) {
            global $WS;
#            if ( $rows > 0 ) $this->setRows( $rows );
            if ( $rows > 1 ) $this->setMultiple($rows);
            if (!isset($WS[$this->name])) {
                return;
            }
            return $this->setDefault($WS[$this->name]);
        }

        function autoDefault($val = 0) {
            $this->autoDefault = $val;
        }

        function setDefault( $def=null ) {
            # if a default has been specified explicitly then don't automatically generate one from the workspace
            $this->autoDefault = 0;
            $this->default = array();
            if ( $def !== null ) {
                if ( is_array( $def ) && $this->multiple ) {
                    reset( $def );
                    foreach($def as $value) {
//                        echo "adding default $value<BR />";
                        $this->default[$value] = 1;
                    }
                } else {
                    if (is_array( $def ) && isset($def[0])) {
                        $def = $def[0];
                    }
                    if (!is_array( $def )) {
                        $def = trim($def);
                        $this->default[$def] = 1;
                    }
                }
            }
            return $this->default;
        }

        function getDefault() {
            return $this->default;
        }

        function getAutoDefault() {
            return $this->autoDefault;
        }

        function getOptions() {
            return $this->options;
        }

        function setOptions( $options ) {
            $this->options = forceArray($options);
        }

        function setExtra( $stuff, $attr = '' ) {
            if(!empty($attr)) {
                $this->extra = updateExtraFromAttribute($stuff, $attr, $this->extra);
            } else {
                $this->extra = $stuff;
            }
            
            return $stuff;
        }

        function setRawOutput( $value ) {
            $this->rawOutput = $value;
            return $value;
        }

        function setName( $name ) {
            $this->name = $name;
            return $name;
        }

        function setDb( $dbh ) {
            $this->dbh = $dbh;
            return $dbh;
        }

        function disable( $value=1 ) {
            $this->disabled = $value;
            return $value;
        }

        function addDefault( $value ) {
            $this->default[$value] = 1;
        }

        function addDefaults( $regexp, $which = 'VALUE' ) {
            if ( is_array($regexp) ) {
                foreach( $regexp as $value ) {
                    $this->default[$value] = 1;
                }
            } else {
                $which=strtoupper($which);
                reset( $this->options );
                foreach($this->options as $key => $value) {
                    if ( ($which=='OPTION' && preg_match("/$regexp/", $key)) || ($which=='VALUE' && preg_match("/$regexp/", $value)) ) {
                        $this->default[$value] = 1;
                    }
                }
            }
        }

        function removeDefaults( $regexp, $which = 'VALUE' ) {
            if ( is_array($regexp) ) {
                reset( $regexp );
                foreach($regexp as $value) {
                    if ( isset($this->default[$value]) ) $this->default[$value]=0;
                }
            } else {
                $which=strtoupper($which);
                reset( $this->options );
                foreach($this->options as $key => $value) {
                    if ( ($which=='OPTION' && preg_match("/$regexp/", $key)) || ($which=='VALUE' && preg_match("/$regexp/", $value)) ) {
                        if ( $this->default[$value] ) $this->default[$value]=0;
                    }
                }
            }
        }

        function printDefault() {
            if ($this->autoDefault > -1) $this->generateDefault($this->autoDefault);
            $output = array();
            foreach( $this->options as $key=>$value ) {
                if( isset($this->default[$value]) ) $output[] = htmlspecialchars($key);
            }
            if (count($output)>1) {
                echo '<LI>'.implode("</LI>\n<LI>",$output).'</LI>';
            } else {
                if (count($output)>0) echo $output[0];
            }
        }

        # Switching on autohide means that if only one option is available then no dropdown is shown
        # use autoHide(true) or autoHide(1) to show the remaining value as text (not a dropdown)
        # use autoHide(2) to have just a hidden form field and nothing else showing
        function autoHide($newVal=TRUE) {
            $this->autoHide = $newVal;
        }

        function redisplay( $name, $extra = null, $def = null, $dontEscape = null ) {
            $this->setName($name);
            $this->autoDefault = 1;
            $this->display( $extra, $def, $dontEscape );
        }

        function redisplayDisabled( $name, $def = null ) {
            $this->setName($name);
            if($def !== null) $this->setDefault($def);
            $this->autoDefault = 1;
            $this->displayDisabled();
        }

        function redisplayCheckboxes( $name, $extra = null, $def = null, $dontEscape = null ) {
            $this->setName($name);
            $this->autoDefault = 1;
            $this->displayCheckboxes( $extra, $def, $dontEscape );
        }

        // this is for use in non-edit mode
        // it displays the option associated with the default - if there are multiple defaults (ie a multiple select box)
        // then they are displayed as a comma separated list
        // $txtIfEmpty is shown if $defaults is blank. e.g. pass in 'None Found' or somesuch
        function showDefault($txtIfEmpty='') {
            if ($this->autoDefault > -1) $this->generateDefault($this->autoDefault);
            $defaults=array();
            reset($this->options);

            foreach($this->options as $key => $value) {
                if (!isset($this->default[$value])) continue;
                $defaults[] = $key;
            }
            $defaultString = implode(', ',$defaults);

            if($defaultString == '')
                $defaultString = $txtIfEmpty;

            if ($this->rawOutput) {
                echo $defaultString;
            } else {
                echo htmlspecialchars($defaultString);
            }

            return $defaults;
        }

        function doHidden($showValue=true) {
            $this->isHidden = TRUE;
            reset($this->options);
            $key = array_key_first($this->options);
            $val = $this->options[$key];
            if ($showValue) {
                echo htmlspecialchars($key);
            }
            echo '<INPUT type="hidden" name="'.$this->name.($this->multiple?'[]':'').'" value="'.htmlspecialchars($val).'">';
        }


        function displayDisabled() {
            // box is disabled - show default option and print a hidden form input with the appropriate value
            $this->showDefault();
            $defaults = $this->default;
            if (!$this->multiple) {
                $defaults = array_key_first($defaults);
            }
            formHidden($this->name, $defaults);
        }

        function getTitle() {
            return ucFirst(preg_replace('/[^a-z ]/i','',fromCamelCase($this->name)));
        }

        function display( $extra = null, $def = null, $dontEscape = null ) {
            if ($this->disabled) {
                $this->displayDisabled();
                return 1;
            }

            if ($extra <> null) {
                $extra = $this->extra.' '.$extra;
            } else {
                $extra = isset($this->extra)?$this->extra:'';
            }
            if ($this->autoDefault > 0) $this->generateDefault($this->autoDefault);
            if ($def <> null) $this->setDefault($def);
            if ($dontEscape <> null) $this->setRawOutput($dontEscape);
            if ( is_array($this->default) && ( count($this->default) > 1 ) ) $this->setMultiple('yes');

            if($this->autoHide && !(count($this->options) > 1)) {
                $this->doHidden($this->autoHide==1);
            } else {
                $this->isHidden = FALSE;
                echo '<SELECT ';
                if ($this->multiple) echo 'MULTIPLE="YES" ';
                if ($this->rows <> '') echo "SIZE=\"".$this->rows."\" ";
                echo "$extra ";
                echo 'TITLE="'.$this->getTitle().'" ';
                echo "NAME=\"$this->name";
                if ($this->multiple) echo '[]';
                echo "\">\n";
                reset( $this->options );
                $i=1;
                $haveSelected = 0;
                foreach($this->options as $key => $value) {
                    echo "<OPTION ";
                    // handle defaults
                    $checked = '';
                    if ( !$this->multiple ) {
                        if ( (count($this->default)==0 && !$haveSelected) || array_key_exists((string)$value,$this->default) ) {
                            echo 'selected ';
                            $haveSelected = 1;
                        }
                    } elseif ( array_key_exists((string)trim($value),$this->default) ) {
                        echo 'selected ';
                    }
                    if(isset($this->extraOptionAttributes[$value])) {
                        echo ' '.$this->extraOptionAttributes[$value].' ';
                    }
                    if ($this->rawOutput) {
                        echo 'VALUE="'.$value.'">'.$key."</OPTION>\n";
                    } else {
                        echo 'VALUE="'.htmlspecialchars($value).'">'.htmlspecialchars($key)."&nbsp;&nbsp;&nbsp;</OPTION>\n";
                    }
                    $i++;
                }
                echo "</SELECT>";
            }
        }

        function displayCheckboxes($extraIdContent = '') {
            if ($this->disabled) {
                $this->displayDisabled();
                return 1;
            }

            global $WS,$USER_TYPE;

            static $previousCheckboxNames;
            if (!isset($previousCheckboxNames)) {
                $previousCheckboxNames = array();
            }
            if (isset($previousCheckboxNames[$this->name])) {
                $previousCheckboxNames[$this->name]++;
                $extraIdContent .= '_'.$previousCheckboxNames[$this->name];
            } else {
                $previousCheckboxNames[$this->name]=0;
            }

            if ($this->autoDefault > 0) $this->generateDefault($this->autoDefault);

            if ($this->autoHide && !(count($this->options) > 1)) {
                $this->doHidden($this->autoHide==1);
                return(0);
            }

            $input_str = '';
            if($USER_TYPE == 'adminUser') {
                $input_str .= '<div %s>';
            } else {
                $input_str .= '<span class="checkbox" %s>';
            }
            $input_str .= '<input id="input_%s%s_%d" type="%s" name="%s" value="%s" %s %s /><label for="input_%s%s_%d"><span></span>%s</label>%s';
            if($USER_TYPE == 'adminUser') {
                $input_str .= '</div>';
            } else {
                $input_str .= '</span><br>';
            }
            $input_str .= "\n";
            $input_name = $this->name;
            $input_type = 'radio';
            if ($this->multiple) {
                $input_name .= '[]';
                $input_type = 'checkbox';
            }

            $already_checked_one = false;
            $idx=0;
            foreach ($this->options as $key => $value) {
                $idx++;
                $checked = '';
                if ( !$this->multiple ) {
                    // See ASD-605 - Changed at Taila's request
                    // So that radio buttons are allowed to be displayed with none of them checked.
                    if ( !$already_checked_one && isset($this->default[$value]) ) {
                        $checked = ' checked';
                        $already_checked_one = true;
                    }
                } elseif ( array_key_exists($value,$this->default) ) {
                    $checked = ' checked';
                }
                $marker = '';
                $checkboxClass = '';
                $extra = $this->extra;
                if(preg_match('/^(.*)\s*{{(.*)}}$/', $key, $matches)) {
                    $key = $matches[1];
                    $marker = $matches[2];
                    if(substr($marker, 0, 6) == 'class:') {
                        $checkboxClass = substr($marker, 6);
                        if(strpos($this->extra, 'class=') !== false) {
                            $extra = preg_replace('/(class=["\'])/', '$1'.$checkboxClass.' ', $extra);
                        } else {
                            $extra .= ' class="'.$checkboxClass.'"';
                        }
                        $marker = '';
                    }
                }
                $afterLabelContent = '';
                if(is_callable($this->afterLabelCallback)) {
                    $afterLabelContent = $this->afterLabelCallback->__invoke($input_type, "input_{$input_name}{$extraIdContent}_$idx", $input_name, $value, $marker);
                } else if(is_callable($this->afterLabelContentIfSelected)) {
                    $afterLabelContent = 
                    '<div class="only-if-selected">'.
                    $this->afterLabelContentIfSelected->__invoke($input_type, "input_{$input_name}{$extraIdContent}_$idx", $input_name, $value, $marker).
                    '</div>';
                }

                $extraOptionAttributes = '';
                if(is_array($this->extraOptionAttributes) && isset($this->extraOptionAttributes[$value])) {
                    $extraOptionAttributes = $this->extraOptionAttributes[$value];
                }
                else if(is_callable($this->extraOptionAttributes)) {
                    $extraOptionAttributes = $this->extraOptionAttributes->__invoke($value);
                }
                
                printf($input_str, $extraOptionAttributes, $input_name, $extraIdContent, $idx, $input_type, $input_name, $value, $checked, $extra, $input_name, $extraIdContent, $idx, $key, $afterLabelContent);
            }
        }

    }
?>
