import React from 'react';
import { storiesOf } from '@storybook/react';

import breadcrumbs from './breadcrumbs.twig'
const breadcrumbsTwig = (
  breadcrumbs({
    breadcrumb: {
      1: {
        url: "#",
        text: "Home"
      },
      2: {
        url: "#",
        text: "Parent Page"
      },
      3: {
        text: "Current Page"
      }
    }
  })
)

/**
 * Add storybook definition for Breadcrumbs.
 */
storiesOf('Molecules/Menus', module)
  .add('breadcrumbs', () =>
    <div dangerouslySetInnerHTML={{__html: breadcrumbsTwig}}></div>
  )
