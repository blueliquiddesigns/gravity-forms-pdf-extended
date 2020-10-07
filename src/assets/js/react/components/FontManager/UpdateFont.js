/* Dependencies */
import React from 'react'
import PropTypes from 'prop-types'
import { sprintf } from 'sprintf-js'
/* Components */
import FontVariant from './FontVariant'
import Kashida from './Kashida'
import AddFontFooter from './AddFontFooter'

/**
 * @package     Gravity PDF
 * @copyright   Copyright (c) 2020, Blue Liquid Designs
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       6.0
 */

/**
 * Display update font panel UI
 *
 * @param id
 * @param fontList
 * @param label
 * @param onHandleInputChange
 * @param onHandleKashidaChange
 * @param onHandleUpload
 * @param onHandleDeleteFontStyle
 * @param onHandleCancelEditFont
 * @param onHandleCancelEditFontKeypress
 * @param onHandleSubmit
 * @param fontStyles
 * @param kashida
 * @param validateLabel
 * @param validateRegular
 * @param disableUpdateButton
 * @param msg
 * @param loading
 * @param tabIndexFontName
 * @param tabIndexFontFiles
 * @param tabIndexKashida
 * @param tabIndexFooterButtons
 *
 * @since 6.0
 */
export const UpdateFont = (
  {
    id,
    fontList,
    label,
    onHandleInputChange,
    onHandleKashidaChange,
    onHandleUpload,
    onHandleDeleteFontStyle,
    onHandleCancelEditFont,
    onHandleCancelEditFontKeypress,
    onHandleSubmit,
    fontStyles,
    kashida,
    validateLabel,
    validateRegular,
    disableUpdateButton,
    msg,
    loading,
    tabIndexFontName,
    tabIndexFontFiles,
    tabIndexKashida,
    tabIndexFooterButtons
  }
) => {
  const font = fontList && fontList.filter(font => font.id === id)[0]
  const useOTL = font && font.useOTL
  const fontNameLabel = sprintf(GFPDF.fontManagerFontNameLabel, "<span class='required'>", '</span>')

  return (
    <div data-test='component-UpdateFont' className='update-font'>
      <form onSubmit={onHandleSubmit}>
        <h2>{GFPDF.fontManagerUpdateTitle}</h2>

        <p>{GFPDF.fontManagerUpdateDesc}</p>

        <label htmlFor='gfpdf-font-name-input' dangerouslySetInnerHTML={{ __html: fontNameLabel }} />

        <p id='gfpdf-font-name-desc'>{GFPDF.fontManagerFontNameDesc}</p>

        <input
          type='text'
          id='gfpdf-update-font-name-input'
          className={!validateLabel ? 'input-label-validation-error' : ''}
          aria-describedby='gfpdf-font-name-desc'
          name='label'
          value={label}
          maxLength='60'
          onChange={e => onHandleInputChange(e, 'updateFont')}
          tabIndex={tabIndexFontName}
        />

        {!validateLabel && (
          <span className='required'>
            <em>{GFPDF.fontManagerFontNameValidationError}</em>
          </span>
        )}

        <label id='gfpdf-font-files-label'>{GFPDF.fontManagerFontFilesLabel}</label>

        <p id='gfpdf-font-files-description'>{GFPDF.fontManagerFontFilesDesc}</p>

        <FontVariant
          state='updateFont'
          fontStyles={fontStyles}
          validateRegular={validateRegular}
          onHandleUpload={onHandleUpload}
          onHandleDeleteFontStyle={onHandleDeleteFontStyle}
          msg={msg}
          tabIndex={tabIndexFontFiles}
        />

        {useOTL > 0 && (
          <Kashida
            kashida={kashida}
            onHandleKashidaChange={onHandleKashidaChange}
            tabIndex={tabIndexKashida}
          />
        )}

        <AddFontFooter
          id={id}
          disabled={disableUpdateButton}
          onHandleCancelEditFont={onHandleCancelEditFont}
          onHandleCancelEditFontKeypress={onHandleCancelEditFontKeypress}
          msg={msg}
          loading={loading}
          tabIndex={tabIndexFooterButtons}
        />
      </form>
    </div>
  )
}

/**
 * PropTypes
 *
 * @since 6.0
 */
UpdateFont.propTypes = {
  id: PropTypes.string,
  fontList: PropTypes.arrayOf(PropTypes.object),
  label: PropTypes.string.isRequired,
  onHandleInputChange: PropTypes.func.isRequired,
  onHandleKashidaChange: PropTypes.func,
  onHandleUpload: PropTypes.func.isRequired,
  onHandleDeleteFontStyle: PropTypes.func.isRequired,
  onHandleCancelEditFont: PropTypes.func.isRequired,
  onHandleCancelEditFontKeypress: PropTypes.func.isRequired,
  onHandleSubmit: PropTypes.func.isRequired,
  validateLabel: PropTypes.bool.isRequired,
  validateRegular: PropTypes.bool.isRequired,
  disableUpdateButton: PropTypes.bool.isRequired,
  fontStyles: PropTypes.object.isRequired,
  kashida: PropTypes.number,
  msg: PropTypes.object.isRequired,
  loading: PropTypes.bool.isRequired,
  tabIndexFontName: PropTypes.string.isRequired,
  tabIndexFontFiles: PropTypes.string.isRequired,
  tabIndexKashida: PropTypes.string.isRequired,
  tabIndexFooterButtons: PropTypes.string.isRequired
}

export default UpdateFont
