import React from "react";

const Input = ({ type, placeholder, className, id, value, onChange }) => {
  return (
    <input
      type={type}
      className={className}
      placeholder={placeholder}
      id={id}
      value={value}
      onChange={onChange}
    />
  );
};

export default Input;
