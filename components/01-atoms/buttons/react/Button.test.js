import React from 'react';
import renderer from 'react-test-renderer';
import { render, fireEvent, screen } from '@testing-library/react';

import Button from './Button.component';

describe('Button', () => {
  it('renders a button component around the given children', () => {
    expect.assertions(1);
    expect(renderer.create(<Button>Click Me!</Button>).toJSON())
      .toMatchInlineSnapshot(`
      <button
        className="button"
        onClick={[Function]}
        type="button"
      >
        Click Me!
      </button>
    `);
  });
  it('calls the given callback function when clicked', () => {
    expect.assertions(1);
    const cb = jest.fn();
    render(<Button onClick={cb}>Click Me!</Button>);
    fireEvent.click(screen.getByText('Click Me!'));
    expect(cb).toHaveBeenCalledTimes(1);
  });
  it('calls the default onClick fn if one is not provided', () => {
    // @TODO: onClick should likely not even need to have a default
    // fn, it should probably just default to null. This test does
    // not really do anything.
    expect(Button.defaultProps.onClick()).toBe(undefined);
  });
});
