import React from 'react';
import { storiesOf } from '@storybook/react';

import status from './status.twig'
const statusTwig = (
  status({
    message_list: {
      status: {
        message: "This is a status message",
      },
      warning: {
        message: "This is a warning message"
      },
      error: {
        message: "This is an error message"
      }
    }
  })
)

/**
 * Add storybook definition for Statuses.
 */
storiesOf('Molecules/Status', module)
  .add('status', () =>
    <div dangerouslySetInnerHTML={{__html: statusTwig}}></div>
  )
